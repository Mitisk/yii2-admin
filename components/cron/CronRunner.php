<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\cron;

use Mitisk\Yii2Admin\models\CronLog;
use Mitisk\Yii2Admin\models\CronTask;
use Mitisk\Yii2Admin\models\Settings;
use Yii;
use yii\helpers\FileHelper;

/**
 * Диспетчер планировщика. Раз в минуту его вызывает единственная строка crontab сервера
 * (`php yii admin/cron/run`); задачи — из кода проекта (Module::$cronTasks) и созданные в /admin/cron/.
 *
 * За один вызов:
 * - под замком забирает минуты: от последней обработанной до текущей. Пропущенные минуты (сервер
 *   или крон стоял) догоняются, но не старше часа; первый в истории вызов берёт только текущую минуту.
 *   Минуту обрабатывает ровно один вызов, даже если в crontab две строки;
 * - собирает задачи, которым пора, в группы по первой подходящей минуте и запускает группы по
 *   очереди (при догоне ночной порядок сохраняется), задачи группы — параллельно;
 * - каждую задачу держит замком-файлом, пока идёт её процесс: идущая задача второй раз не стартует,
 *   а упавший диспетчер замок отпускает сам;
 * - пишет итог в журнал и чистит журнал от старых записей.
 */
final class CronRunner
{
    /** Сколько байт вывода команды хранится в журнале */
    public const OUTPUT_LIMIT = 20000;

    /** Насколько давние пропущенные минуты ещё выполняются, секунд */
    public const CATCH_UP_SECONDS = 3600;

    /** Диспетчер молчит дольше — раздел подсказывает, как его подключить, секунд */
    public const SILENCE_AFTER = 180;

    /** Сколько ждать замок соседнего вызова при разборе минут, секунд */
    private const CLAIM_WAIT_SECONDS = 5;

    /** @var callable(array<int, array{command: string[], timeout: int}>): array<int, array{exit: int|null, output: string, duration: int, timedOut: bool}> */
    private $launcher;

    /**
     * @param callable|null $launcher запуск процессов группы; по умолчанию — ProcessLauncher через точку входа консоли
     */
    public function __construct(?callable $launcher = null)
    {
        $this->launcher = $launcher ?? new ProcessLauncher(PHP_BINARY, self::consoleScript());
    }

    /**
     * @return array{started: int, ok: int, failed: int, skipped: int, minutes: int, problems: string[]}
     *         problems — сбои и пропуски словами (успешные запуски сюда не попадают)
     */
    public function run(?int $now = null): array
    {
        $now ??= time();
        $realStart = time();
        Yii::$app->settings->set('HIDDEN', 'cron_heartbeat', (string) $now, 'integer');
        CodeTasks::sync();

        $summary = ['started' => 0, 'ok' => 0, 'failed' => 0, 'skipped' => 0, 'minutes' => 0, 'problems' => []];
        $minutes = $this->claimMinutes($now);
        $summary['minutes'] = count($minutes);
        if (count($minutes) > 1) {
            $summary['problems'][] = 'Догоняю пропущенные минуты: ' . (count($minutes) - 1) . '.';
        }
        if ($minutes !== []) {
            foreach ($this->groups($minutes) as $group) {
                $this->runGroup($group, $now + max(0, time() - $realStart), $summary);
            }
        }

        $this->cleanLog($now);

        return $summary;
    }

    /**
     * Когда диспетчер запускался в последний раз (unix time) или null, если ещё ни разу.
     */
    public static function heartbeat(): ?int
    {
        $value = (int) Yii::$app->settings->get('HIDDEN', 'cron_heartbeat', 0);

        return $value > 0 ? $value : null;
    }

    /**
     * Файл-замок задачи: его держит процесс диспетчера, пока задача выполняется.
     */
    public static function lockFile(int $taskId): string
    {
        $dir = Yii::getAlias('@runtime/admin-cron');
        FileHelper::createDirectory($dir);

        return $dir . DIRECTORY_SEPARATOR . 'task-' . $taskId . '.lock';
    }

    /**
     * Самоочистка журнала: записи старше CRON.log_days дней и всё сверх CRON.log_rows последних на задачу.
     */
    public function cleanLog(int $now): void
    {
        $days = max(1, (int) Yii::$app->settings->get('CRON', 'log_days', 14));
        $rows = max(1, (int) Yii::$app->settings->get('CRON', 'log_rows', 200));

        CronLog::deleteAll(['<', 'started_at', $now - $days * 86400]);

        $crowded = CronLog::find()
            ->select('task_id')
            ->groupBy('task_id')
            ->having(['>', 'COUNT(*)', $rows])
            ->column();
        foreach ($crowded as $taskId) {
            // Самая старая запись, которая ещё остаётся: всё, что старше, удаляется
            $edge = CronLog::find()
                ->where(['task_id' => $taskId])
                ->orderBy(['started_at' => SORT_DESC, 'id' => SORT_DESC])
                ->offset($rows - 1)
                ->limit(1)
                ->one();
            if ($edge === null) {
                continue;
            }
            CronLog::deleteAll([
                'and',
                ['task_id' => $taskId],
                ['or',
                    ['<', 'started_at', $edge->started_at],
                    ['and', ['started_at' => $edge->started_at], ['<', 'id', $edge->id]],
                ],
            ]);
        }
    }

    /**
     * Минуты, которые достаются этому вызову (начала минут по возрастанию). Пусто — минуту уже
     * обработал соседний вызов или замок разбора занят дольше CLAIM_WAIT_SECONDS.
     *
     * @return int[]
     */
    private function claimMinutes(int $now): array
    {
        $current = intdiv($now, 60) * 60;
        $lockPath = Yii::getAlias('@runtime/admin-cron');
        FileHelper::createDirectory($lockPath);
        $lock = @fopen($lockPath . DIRECTORY_SEPARATOR . 'claim.lock', 'c');
        if ($lock === false) {
            return [];
        }
        try {
            $deadline = microtime(true) + self::CLAIM_WAIT_SECONDS;
            while (!flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) > $deadline) {
                    return [];
                }
                usleep(100000);
            }

            // Мимо кэша настроек: значение меняют соседние процессы
            $last = (int) Settings::find()
                ->select('value')
                ->where(['model_name' => 'HIDDEN', 'attribute' => 'cron_last_minute'])
                ->scalar();
            if ($last === $current) {
                return [];
            }
            $from = $current;
            if ($last > 0 && $last < $current) {
                $from = max($last + 60, $current - self::CATCH_UP_SECONDS + 60);
            }
            Yii::$app->settings->set('HIDDEN', 'cron_last_minute', (string) $current, 'integer');

            return range($from, $current, 60);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Задачи, которым пора, по группам первой подходящей минуты; «Запустить сейчас» — в группу текущей.
     *
     * @param int[] $minutes
     * @return array<int, array<int, array{task: CronTask, source: string}>>
     */
    private function groups(array $minutes): array
    {
        $tasks = CronTask::find()->orderBy(['id' => SORT_ASC])->all();
        $groups = [];
        $taken = [];
        foreach ($minutes as $minute) {
            foreach ($tasks as $task) {
                if (isset($taken[$task->id]) || !$task->enabled) {
                    continue;
                }
                if (CronExpression::isDue((string) $task->schedule, $minute)) {
                    $taken[$task->id] = true;
                    $groups[$minute][] = ['task' => $task, 'source' => CronLog::SOURCE_SCHEDULE];
                }
            }
        }
        $current = (int) end($minutes);
        foreach ($tasks as $task) {
            if ($task->run_requested_at !== null && !isset($taken[$task->id])) {
                $groups[$current][] = ['task' => $task, 'source' => CronLog::SOURCE_MANUAL];
            }
        }
        ksort($groups);

        return array_values($groups);
    }

    /**
     * Запускает задачи группы параллельно и ждёт их. Задача, чей замок занят, пропускается.
     *
     * @param array<int, array{task: CronTask, source: string}> $group
     * @param array{started: int, ok: int, failed: int, skipped: int, minutes: int, problems: string[]} $summary
     */
    private function runGroup(array $group, int $startedAt, array &$summary): void
    {
        $jobs = [];
        $locks = [];
        $sources = [];
        foreach ($group as ['task' => $task, 'source' => $source]) {
            $lock = @fopen(self::lockFile((int) $task->id), 'c');
            if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                if ($lock !== false) {
                    fclose($lock);
                }
                $summary['skipped']++;
                $message = '«' . $task->name . '» (' . $task->command . ') пропущена — прошлый запуск ещё идёт.';
                $summary['problems'][] = $message;
                Yii::warning('Планировщик: ' . $message, 'cron');
                continue;
            }
            $locks[$task->id] = $lock;
            $sources[$task->id] = $source;
            CronTask::updateAll(['running_since' => $startedAt, 'run_requested_at' => null], ['id' => $task->id]);
            $jobs[$task->id] = ['command' => $task->commandParts(), 'timeout' => (int) $task->timeout];
        }

        if ($jobs === []) {
            return;
        }
        try {
            $results = ($this->launcher)($jobs);
            foreach ($group as ['task' => $task]) {
                if (!isset($jobs[$task->id])) {
                    continue;
                }
                $result = $results[$task->id] ?? null;
                $status = $this->record($task, $sources[$task->id], $startedAt, $result);
                $summary['started']++;
                $summary[$status === CronTask::STATUS_OK ? 'ok' : 'failed']++;
                if ($status !== CronTask::STATUS_OK) {
                    $message = '«' . $task->name . '» (' . $task->command . ') '
                        . ($status === CronTask::STATUS_TIMEOUT
                            ? 'не уложилась в лимит времени и остановлена.'
                            : 'завершилась с ошибкой, код ' . ($result['exit'] ?? '—') . '.');
                    $summary['problems'][] = $message;
                    Yii::error('Планировщик: ' . $message, 'cron');
                }
            }
        } finally {
            foreach ($locks as $lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * @param array{exit: int|null, output: string, duration: int, timedOut: bool}|null $result
     */
    private function record(CronTask $task, string $source, int $startedAt, ?array $result): string
    {
        $result ??= ['exit' => null, 'output' => 'Процесс не вернул результат.', 'duration' => 0, 'timedOut' => false];
        $status = !empty($result['timedOut'])
            ? CronTask::STATUS_TIMEOUT
            : ($result['exit'] === 0 ? CronTask::STATUS_OK : CronTask::STATUS_FAIL);
        $duration = max(0, (int) $result['duration']);

        $output = (string) $result['output'];
        if (!mb_check_encoding($output, 'UTF-8')) {
            $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
        }
        if (strlen($output) > self::OUTPUT_LIMIT) {
            // Конец вывода важнее начала: там обычно ошибка
            $output = "… вывод обрезан, показан конец …\n" . mb_strcut($output, strlen($output) - self::OUTPUT_LIMIT, self::OUTPUT_LIMIT, 'UTF-8');
        }
        if ($status === CronTask::STATUS_TIMEOUT) {
            $output = trim($output . "\nЗадача не уложилась в лимит времени и остановлена.");
        }

        // «Только ошибки»: из успешных запусков остаётся последний
        if ($status === CronTask::STATUS_OK && $task->log_mode === CronTask::LOG_FAILURES) {
            CronLog::deleteAll(['task_id' => $task->id, 'status' => CronTask::STATUS_OK]);
        }

        $log = new CronLog([
            'task_id' => $task->id,
            'started_at' => $startedAt,
            'finished_at' => $startedAt + (int) ceil($duration / 1000),
            'duration_ms' => $duration,
            'status' => $status,
            'exit_code' => $result['exit'],
            'source' => $source,
            'output' => $output,
        ]);
        $log->save(false);

        CronTask::updateAll([
            'running_since' => null,
            'last_run_at' => $startedAt,
            'last_status' => $status,
            'last_duration_ms' => $duration,
        ], ['id' => $task->id]);

        return $status;
    }

    /**
     * Точка входа консоли проекта (файл yii): в консольном запросе — тот самый скрипт, которым запущен диспетчер.
     */
    private static function consoleScript(): string
    {
        $request = Yii::$app->request;
        if ($request instanceof \yii\console\Request) {
            $script = realpath((string) $request->getScriptFile());
            if ($script !== false) {
                return $script;
            }
        }

        return Yii::getAlias('@app/yii');
    }
}
