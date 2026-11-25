<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\cron;

use Mitisk\Yii2Admin\models\CronLog;
use Mitisk\Yii2Admin\models\CronTask;
use Yii;

/**
 * Диспетчер планировщика. Раз в минуту его вызывает единственная строка crontab сервера
 * (`php yii admin/cron/run`), а расписание задач живёт в админке (/admin/cron/).
 *
 * За один вызов: находит задачи, которым пора (по расписанию или по кнопке «Запустить сейчас»),
 * запускает их отдельными процессами, пишет итог в журнал и чистит журнал от старых записей.
 * Задача, которая ещё идёт с прошлого раза, второй раз не запускается.
 */
final class CronRunner
{
    /** Сколько байт вывода команды хранится в журнале */
    public const OUTPUT_LIMIT = 20000;

    /** Запас к лимиту времени задачи, после которого «идущая» задача считается зависшей, секунд */
    private const STALE_MARGIN = 300;

    /** Диспетчер молчит дольше — раздел подсказывает, как его подключить, секунд */
    public const SILENCE_AFTER = 180;

    /** @var callable(array<int, array{command: string[], timeout: int}>): array<int, array{exit: int|null, output: string, duration: int, timedOut: bool}> */
    private $launcher;

    /**
     * @param callable|null $launcher запуск процессов; по умолчанию — ProcessLauncher через точку входа консоли
     */
    public function __construct(?callable $launcher = null)
    {
        $this->launcher = $launcher ?? new ProcessLauncher(PHP_BINARY, self::consoleScript());
    }

    /**
     * @return array{started: int, ok: int, failed: int}
     */
    public function run(?int $now = null): array
    {
        $now ??= time();
        Yii::$app->settings->set('HIDDEN', 'cron_heartbeat', (string) $now, 'integer');

        $jobs = [];
        $sources = [];
        foreach (CronTask::find()->orderBy(['id' => SORT_ASC])->all() as $task) {
            $source = $this->sourceFor($task, $now);
            if ($source === null) {
                continue;
            }
            // Условное обновление: два диспетчера одной минуты не возьмут задачу вдвоём
            $taken = CronTask::updateAll(
                ['running_since' => $now, 'run_requested_at' => null],
                ['and', ['id' => $task->id], $task->running_since === null ? ['running_since' => null] : ['running_since' => $task->running_since]]
            );
            if ($taken !== 1) {
                continue;
            }
            $jobs[$task->id] = ['command' => $task->commandParts(), 'timeout' => (int) $task->timeout];
            $sources[$task->id] = $source;
        }

        $summary = ['started' => count($jobs), 'ok' => 0, 'failed' => 0];
        if ($jobs !== []) {
            $results = ($this->launcher)($jobs);
            foreach ($jobs as $id => $job) {
                $status = $this->record($id, $sources[$id], $now, $results[$id] ?? null);
                $summary[$status === CronTask::STATUS_OK ? 'ok' : 'failed']++;
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
            // id самой старой записи, которая ещё остаётся: всё, что старше, удаляется
            $edge = CronLog::find()
                ->select('id')
                ->where(['task_id' => $taskId])
                ->orderBy(['started_at' => SORT_DESC, 'id' => SORT_DESC])
                ->offset($rows - 1)
                ->limit(1)
                ->scalar();
            if ($edge === false || $edge === null) {
                continue;
            }
            $edgeRow = CronLog::findOne((int) $edge);
            if ($edgeRow === null) {
                continue;
            }
            CronLog::deleteAll([
                'and',
                ['task_id' => $taskId],
                ['or',
                    ['<', 'started_at', $edgeRow->started_at],
                    ['and', ['started_at' => $edgeRow->started_at], ['<', 'id', $edgeRow->id]],
                ],
            ]);
        }
    }

    /**
     * Почему задачу надо запустить сейчас: 'manual', 'schedule' или null — не надо.
     */
    private function sourceFor(CronTask $task, int $now): ?string
    {
        if ($task->running_since !== null && $now - (int) $task->running_since <= (int) $task->timeout + self::STALE_MARGIN) {
            return null;
        }
        if ($task->run_requested_at !== null) {
            return CronLog::SOURCE_MANUAL;
        }
        if (!$task->enabled || !CronExpression::isDue((string) $task->schedule, $now)) {
            return null;
        }
        // Уже запускалась в эту минуту (crontab дёрнул диспетчер дважды)
        if ($task->last_run_at !== null && intdiv((int) $task->last_run_at, 60) === intdiv($now, 60)) {
            return null;
        }

        return CronLog::SOURCE_SCHEDULE;
    }

    /**
     * @param array{exit: int|null, output: string, duration: int, timedOut: bool}|null $result
     */
    private function record(int $taskId, string $source, int $startedAt, ?array $result): string
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

        $log = new CronLog([
            'task_id' => $taskId,
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
        ], ['id' => $taskId]);

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
