<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\cron;

use Mitisk\Yii2Admin\components\cron\CodeTasks;
use Mitisk\Yii2Admin\components\cron\CronRunner;
use Mitisk\Yii2Admin\components\cron\ProcessLauncher;
use Mitisk\Yii2Admin\models\CronLog;
use Mitisk\Yii2Admin\models\CronTask;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Задачи из кода проекта (Module::$cronTasks), догон пропущенных минут, замок задачи,
 * лимит времени «без лимита» и журнал «только ошибки».
 */
final class CronCodeTasksTest extends TestCase
{
    /** @var array<int, string[]> группы запусков: команды каждой группы в порядке вызова лаунчера */
    private array $groups = [];

    /** @var array<string, mixed> */
    private array $result = ['exit' => 0, 'output' => '', 'duration' => 10, 'timedOut' => false];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCodeTasks([]);
    }

    // --- Задачи из кода ---

    public function testCodeTasksAreSyncedAsReadOnlyRows(): void
    {
        $this->setCodeTasks([
            ['key' => 'account-purge', 'name' => 'Очистка удалённых аккаунтов', 'command' => 'account/purge', 'schedule' => '30 1 * * *', 'log' => 'all'],
            ['key' => 'evening-tick', 'command' => 'evening/tick', 'schedule' => '* * * * *', 'log' => 'failures'],
        ]);

        CodeTasks::sync();

        $purge = CronTask::findOne(['code_key' => 'account-purge']);
        self::assertNotNull($purge);
        self::assertTrue($purge->isFromCode());
        self::assertSame('Очистка удалённых аккаунтов', $purge->name);
        self::assertSame('account/purge', $purge->command);
        self::assertSame('30 1 * * *', $purge->schedule);
        self::assertSame(0, (int) $purge->timeout, 'У задач из кода по умолчанию нет лимита времени');
        self::assertSame(1, (int) $purge->enabled);

        $tick = CronTask::findOne(['code_key' => 'evening-tick']);
        self::assertSame('evening/tick', $tick->name, 'Без названия — команда');
        self::assertSame(CronTask::LOG_FAILURES, $tick->log_mode);
    }

    public function testCodeWinsOverChangesInDatabase(): void
    {
        $this->setCodeTasks([['key' => 'purge', 'command' => 'account/purge', 'schedule' => '30 1 * * *']]);
        CodeTasks::sync();
        CronTask::updateAll(['schedule' => '0 5 * * *', 'enabled' => 0, 'command' => 'cache/flush-all'], ['code_key' => 'purge']);

        CodeTasks::sync();

        $task = CronTask::findOne(['code_key' => 'purge']);
        self::assertSame('30 1 * * *', $task->schedule);
        self::assertSame('account/purge', $task->command);
        self::assertSame(1, (int) $task->enabled, 'Задачу из кода выключили в базе');
    }

    public function testTaskRemovedFromCodeIsDeletedWithItsLog(): void
    {
        $this->setCodeTasks([['key' => 'old', 'command' => 'old/task', 'schedule' => '0 3 * * *']]);
        CodeTasks::sync();
        $id = (int) CronTask::findOne(['code_key' => 'old'])->id;
        (new CronLog(['task_id' => $id, 'started_at' => time(), 'status' => 'ok', 'source' => 'schedule']))->save(false);

        $this->setCodeTasks([]);
        CodeTasks::sync();

        self::assertNull(CronTask::findOne($id));
        self::assertSame(0, (int) CronLog::find()->where(['task_id' => $id])->count());
    }

    public function testTasksCreatedInAdminAreNotTouchedBySync(): void
    {
        $own = new CronTask(['name' => 'Своя', 'command' => 'cache/flush-all', 'schedule' => '0 4 * * *']);
        self::assertTrue($own->save());

        $this->setCodeTasks([['key' => 'purge', 'command' => 'account/purge', 'schedule' => '30 1 * * *']]);
        CodeTasks::sync();

        self::assertNotNull(CronTask::findOne($own->id));
    }

    public function testInvalidDefinitionIsSkippedAndReported(): void
    {
        $this->setCodeTasks([
            ['key' => 'bad-command', 'command' => 'rm -rf /', 'schedule' => '* * * * *'],
            ['key' => 'bad-schedule', 'command' => 'cache/flush-all', 'schedule' => 'каждый час'],
            ['command' => 'cache/flush-all', 'schedule' => '* * * * *'],
            ['key' => 'good', 'command' => 'cache/flush-all', 'schedule' => '0 3 * * *'],
        ]);

        $errors = CodeTasks::sync();

        self::assertSame(['good'], CronTask::find()->select('code_key')->where(['not', ['code_key' => null]])->column());
        self::assertCount(3, $errors);
        self::assertStringContainsString('bad-command', implode("\n", $errors));
    }

    public function testDefinitionsMayComeFromCallable(): void
    {
        $this->setCodeTasks(static fn (): array => [['key' => 'cb', 'command' => 'cache/flush-all', 'schedule' => '0 3 * * *']]);

        CodeTasks::sync();

        self::assertNotNull(CronTask::findOne(['code_key' => 'cb']));
    }

    public function testDispatcherRunsCodeTasksWithoutUiStep(): void
    {
        $this->setCodeTasks([['key' => 'tick', 'command' => 'evening/tick', 'schedule' => '* * * * *']]);

        $this->runner()->run(mktime(10, 5, 0, 11, 23, 2026));

        self::assertSame([['evening/tick']], $this->groups);
    }

    // --- Догон пропущенных минут ---

    public function testFirstRunDoesNotCatchUp(): void
    {
        $this->setCodeTasks([['key' => 'ten', 'command' => 'ten/run', 'schedule' => '0 10 * * *']]);

        $this->runner()->run(mktime(10, 5, 0, 11, 23, 2026));

        self::assertSame([], $this->groups);
    }

    public function testMissedMinutesAreCaughtUpOnceAndInTimeOrder(): void
    {
        $this->setCodeTasks([
            ['key' => 'refresh', 'command' => 'catalog/refresh-tiers', 'schedule' => '10 0 * * *'],
            ['key' => 'tariffs', 'command' => 'tariff-cron/process-daily', 'schedule' => '5 0 * * *'],
            ['key' => 'tick', 'command' => 'evening/tick', 'schedule' => '* * * * *'],
        ]);
        $this->runner()->run(mktime(0, 0, 0, 11, 23, 2026));
        $this->groups = [];

        // Крон стоял 12 минут
        $this->runner()->run(mktime(0, 12, 0, 11, 23, 2026));

        self::assertSame([['evening/tick'], ['tariff-cron/process-daily'], ['catalog/refresh-tiers']], $this->groups, 'Догон: каждая задача один раз, тарифы раньше пересчёта выдачи');
    }

    public function testCatchUpReachesOneHourBackOnly(): void
    {
        $this->setCodeTasks([
            ['key' => 'old', 'command' => 'old/run', 'schedule' => '30 8 * * *'],
            ['key' => 'recent', 'command' => 'recent/run', 'schedule' => '30 9 * * *'],
        ]);
        $this->runner()->run(mktime(8, 0, 0, 11, 23, 2026));

        $this->runner()->run(mktime(10, 5, 0, 11, 23, 2026));

        self::assertSame([['recent/run']], $this->groups);
    }

    public function testMinuteIsProcessedOnlyOnceByConcurrentDispatchers(): void
    {
        $this->setCodeTasks([['key' => 'tick', 'command' => 'evening/tick', 'schedule' => '* * * * *']]);
        $now = mktime(10, 5, 0, 11, 23, 2026);

        $this->runner()->run($now);
        $this->runner()->run($now + 20);

        self::assertCount(1, $this->groups, 'Две строки крона в одну минуту запустили задачу дважды');
    }

    // --- Замок, лимит, журнал ---

    public function testTaskHeldByRunningProcessIsSkipped(): void
    {
        $this->setCodeTasks([['key' => 'long', 'command' => 'catalog/reindex', 'schedule' => '* * * * *']]);
        CodeTasks::sync();
        $task = CronTask::findOne(['code_key' => 'long']);
        $lock = fopen(CronRunner::lockFile((int) $task->id), 'c');
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        try {
            $this->runner()->run(mktime(10, 5, 0, 11, 23, 2026));
            self::assertSame([], $this->groups, 'Вторая копия задачи поверх идущей');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $this->runner()->run(mktime(10, 6, 0, 11, 23, 2026));
        self::assertSame([['catalog/reindex']], $this->groups, 'Замок освободился — задача снова запускается');
    }

    public function testZeroTimeoutMeansNoLimit(): void
    {
        $launcher = new ProcessLauncher(PHP_BINARY, __FILE__);
        $sleep = ['-r', 'usleep(1500000); echo "готово";'];

        $results = (new \ReflectionMethod($launcher, 'runRaw'))->invoke($launcher, [
            1 => ['args' => $sleep, 'timeout' => 0],
            2 => ['args' => $sleep, 'timeout' => 1],
        ]);

        self::assertSame(0, $results[1]['exit']);
        self::assertFalse($results[1]['timedOut']);
        self::assertStringContainsString('готово', $results[1]['output']);
        self::assertTrue($results[2]['timedOut'], 'Лимит в 1 секунду не сработал');
    }

    public function testFailuresOnlyLogKeepsLastSuccessAndAllFailures(): void
    {
        $this->setCodeTasks([['key' => 'tick', 'command' => 'evening/tick', 'schedule' => '* * * * *', 'log' => 'failures']]);
        $start = mktime(10, 0, 0, 11, 23, 2026);
        foreach ([0, 0, 1, 0, 0] as $i => $exit) {
            $this->result = ['exit' => $exit, 'output' => $exit ? 'ошибка' : '', 'duration' => 10, 'timedOut' => false];
            $this->runner()->run($start + $i * 60);
        }

        $task = CronTask::findOne(['code_key' => 'tick']);
        $logs = CronLog::find()->where(['task_id' => $task->id])->orderBy(['started_at' => SORT_ASC])->all();
        self::assertSame(['fail', 'ok'], array_map(static fn (CronLog $log): string => $log->status, $logs));
        self::assertSame($start + 4 * 60, (int) $logs[1]->started_at, 'Остаётся последний успешный запуск');
    }

    public function testReportNamesProblemsAndStaysQuietOnSuccess(): void
    {
        $this->setCodeTasks([
            ['key' => 'ok', 'name' => 'Тихая', 'command' => 'quiet/run', 'schedule' => '* * * * *'],
            ['key' => 'bad', 'name' => 'Сломанная', 'command' => 'broken/run', 'schedule' => '* * * * *'],
            ['key' => 'busy', 'name' => 'Долгая', 'command' => 'long/run', 'schedule' => '* * * * *'],
        ]);
        CodeTasks::sync();
        $busy = CronTask::findOne(['code_key' => 'busy']);
        $lock = fopen(CronRunner::lockFile((int) $busy->id), 'c');
        flock($lock, LOCK_EX | LOCK_NB);
        $runner = new CronRunner(static function (array $jobs): array {
            $results = [];
            foreach ($jobs as $id => $job) {
                $failed = $job['command'] === ['broken/run'];
                $results[$id] = ['exit' => $failed ? 3 : 0, 'output' => '', 'duration' => 5, 'timedOut' => false];
            }

            return $results;
        });

        try {
            $summary = $runner->run(mktime(10, 5, 0, 11, 23, 2026));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        self::assertSame(1, $summary['failed']);
        $problems = implode("\n", $summary['problems']);
        self::assertStringContainsString('Сломанная', $problems);
        self::assertStringContainsString('код 3', $problems);
        self::assertStringContainsString('Долгая', $problems);
        self::assertStringContainsString('ещё идёт', $problems);
        self::assertStringNotContainsString('Тихая', $problems, 'Успешный запуск попал в отчёт');
    }

    // --- Раздел в админке ---

    public function testCodeTaskIsShownButCannotBeChangedFromAdmin(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->setCodeTasks([['key' => 'purge', 'name' => 'Очистка аккаунтов', 'command' => 'account/purge', 'schedule' => '30 1 * * *']]);

        $list = $this->runAction('admin/cron/index');
        self::assertIsString($list);
        self::assertStringContainsString('Очистка аккаунтов', $list);
        self::assertStringContainsString('из кода', $list);

        $task = CronTask::findOne(['code_key' => 'purge']);
        $form = $this->runAction('admin/cron/update', ['id' => $task->id]);
        self::assertIsString($form);
        self::assertStringNotContainsString('name="CronTask[command]"', $form, 'У задачи из кода редактируемая команда');

        $this->runAction('admin/cron/update', ['id' => $task->id], ['CronTask' => ['schedule' => '0 5 * * *', 'command' => 'cache/flush-all']]);
        $task->refresh();
        self::assertSame('30 1 * * *', $task->schedule);

        $this->runAction('admin/cron/run-now', ['id' => $task->id], []);
        $task->refresh();
        self::assertNotNull($task->run_requested_at, '«Запустить сейчас» для задачи из кода');

        foreach (['toggle', 'delete'] as $action) {
            try {
                $this->runAction('admin/cron/' . $action, ['id' => $task->id], []);
                self::fail($action . ' сработал для задачи из кода');
            } catch (ForbiddenHttpException) {
            }
        }
        self::assertNotNull(CronTask::findOne($task->id));
    }

    // --- Помощники ---

    /**
     * @param array<int, array<string, mixed>>|callable $tasks
     */
    private function setCodeTasks($tasks): void
    {
        Yii::$app->getModule('admin')->cronTasks = $tasks;
    }

    private function runner(): CronRunner
    {
        return new CronRunner(function (array $jobs): array {
            $this->groups[] = array_values(array_map(static fn (array $job): string => implode(' ', $job['command']), $jobs));
            $results = [];
            foreach (array_keys($jobs) as $id) {
                $results[$id] = $this->result;
            }

            return $results;
        });
    }
}
