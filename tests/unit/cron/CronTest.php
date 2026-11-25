<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\cron;

use Mitisk\Yii2Admin\components\cron\CronExpression;
use Mitisk\Yii2Admin\components\cron\CronRunner;
use Mitisk\Yii2Admin\models\CronLog;
use Mitisk\Yii2Admin\models\CronTask;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;

/**
 * Планировщик задач: расписание, запуск, журнал с самоочисткой, раздел /admin/cron/.
 */
final class CronTest extends TestCase
{
    /** @var array<int, array{command: string[], timeout: int}> что диспетчер попросил запустить */
    private array $launched = [];

    /** @var array<string, mixed> ответ «процесса» для следующих запусков */
    private array $result = ['exit' => 0, 'output' => 'готово', 'duration' => 120, 'timedOut' => false];

    // --- Расписание ---

    public function testExpressionMatchesMinutesHoursDaysAndSteps(): void
    {
        $monday0730 = mktime(7, 30, 0, 11, 23, 2026); // понедельник

        self::assertTrue(CronExpression::isDue('* * * * *', $monday0730));
        self::assertTrue(CronExpression::isDue('30 7 * * *', $monday0730));
        self::assertTrue(CronExpression::isDue('*/15 * * * *', $monday0730));
        self::assertTrue(CronExpression::isDue('0,30 7-9 * * 1-5', $monday0730));
        self::assertTrue(CronExpression::isDue('30 7 23 11 *', $monday0730));
        self::assertFalse(CronExpression::isDue('31 7 * * *', $monday0730));
        self::assertFalse(CronExpression::isDue('30 7 * * 0', $monday0730), 'Воскресенье — 0 и 7');
        self::assertFalse(CronExpression::isDue('*/7 * * * *', $monday0730));
    }

    public function testInvalidExpressionsAreRejected(): void
    {
        foreach (['', '* * * *', '60 * * * *', '* 24 * * *', '*/0 * * * *', 'a b c d e', '5-1 * * * *', '* * * * * *'] as $expression) {
            self::assertFalse(CronExpression::isValid($expression), 'Принято: «' . $expression . '»');
        }
        self::assertTrue(CronExpression::isValid('0 3 * * 7'));
    }

    public function testNextRunAndDescription(): void
    {
        $from = mktime(7, 30, 0, 11, 23, 2026);

        self::assertSame(mktime(3, 0, 0, 11, 24, 2026), CronExpression::nextRun('0 3 * * *', $from));
        self::assertSame(mktime(7, 45, 0, 11, 23, 2026), CronExpression::nextRun('*/15 * * * *', $from));
        self::assertSame('каждую минуту', CronExpression::describe('* * * * *'));
        self::assertSame('каждые 15 минут', CronExpression::describe('*/15 * * * *'));
        self::assertSame('каждый день в 03:00', CronExpression::describe('0 3 * * *'));
        self::assertSame('каждый час в :05', CronExpression::describe('5 * * * *'));
    }

    // --- Задача ---

    public function testCommandIsAConsoleRouteNotAShellLine(): void
    {
        self::assertTrue($this->task(['command' => 'cache/flush-all'])->validate());
        self::assertTrue($this->task(['command' => 'catalog/reindex --force=1 full'])->validate());

        foreach (['rm -rf /', 'cache/flush-all; id', 'cache/flush-all && id', '../yii migrate', 'cache/flush `id`', 'admin/cron/run'] as $command) {
            self::assertFalse($this->task(['command' => $command])->validate(['command']), 'Принято: «' . $command . '»');
        }
    }

    public function testScheduleIsValidated(): void
    {
        self::assertFalse($this->task(['schedule' => 'каждый час'])->validate(['schedule']));
        self::assertTrue($this->task(['schedule' => '0 * * * *'])->validate(['schedule']));
    }

    // --- Диспетчер ---

    public function testDueTaskRunsOncePerMinuteAndIsLogged(): void
    {
        $task = $this->savedTask(['command' => 'cache/flush-all --interactive=0', 'schedule' => '*/5 * * * *']);
        $now = mktime(10, 5, 20, 11, 23, 2026);

        $this->runner()->run($now);
        $this->runner()->run($now + 15);

        self::assertCount(1, $this->launched, 'Задача запущена дважды за одну минуту');
        self::assertSame(['cache/flush-all', '--interactive=0'], $this->launched[0]['command']);
        $task->refresh();
        self::assertSame('ok', $task->last_status);
        self::assertNull($task->running_since);
        $log = CronLog::findOne(['task_id' => $task->id]);
        self::assertNotNull($log);
        self::assertSame('готово', $log->output);
        self::assertSame('schedule', $log->source);
        self::assertSame(120, (int) $log->duration_ms);
    }

    public function testTaskOutsideScheduleAndDisabledTaskDoNotRun(): void
    {
        $this->savedTask(['schedule' => '0 3 * * *']);
        $this->savedTask(['schedule' => '* * * * *', 'enabled' => 0, 'name' => 'Выключенная']);

        $this->runner()->run(mktime(10, 5, 0, 11, 23, 2026));

        self::assertSame([], $this->launched);
    }

    public function testRunNowWorksOutsideScheduleAndForDisabledTask(): void
    {
        $task = $this->savedTask(['schedule' => '0 3 * * *', 'enabled' => 0]);
        $task->requestRun();

        $this->runner()->run(mktime(10, 5, 0, 11, 23, 2026));

        self::assertCount(1, $this->launched);
        $task->refresh();
        self::assertNull($task->run_requested_at, 'Запрос «запустить сейчас» выполняется один раз');
        self::assertSame('manual', CronLog::findOne(['task_id' => $task->id])->source);
    }

    public function testFailureAndTimeoutAreRecorded(): void
    {
        $task = $this->savedTask(['schedule' => '* * * * *']);

        $this->result = ['exit' => 2, 'output' => 'Exception: всё плохо', 'duration' => 30, 'timedOut' => false];
        $this->runner()->run(mktime(10, 5, 0, 11, 23, 2026));
        $task->refresh();
        self::assertSame('fail', $task->last_status);

        $this->result = ['exit' => null, 'output' => '', 'duration' => 600000, 'timedOut' => true];
        $this->runner()->run(mktime(10, 6, 0, 11, 23, 2026));
        $task->refresh();
        self::assertSame('timeout', $task->last_status);
        self::assertSame(2, (int) CronLog::find()->where(['task_id' => $task->id])->count());
    }

    public function testTaskStillRunningIsNotStartedAgain(): void
    {
        $task = $this->savedTask(['schedule' => '* * * * *']);
        $now = mktime(10, 5, 0, 11, 23, 2026);
        // Задача идёт: её замок держит процесс диспетчера, запустивший её
        $lock = fopen(CronRunner::lockFile((int) $task->id), 'c');
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        try {
            $this->runner()->run($now);
            self::assertSame([], $this->launched, 'Вторая копия задачи поверх первой');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        // Диспетчер упал, отметка в базе осталась, но замок свободен — задача снова запускается
        $task->updateAttributes(['running_since' => $now - 30]);
        $this->runner()->run($now + 60);
        self::assertCount(1, $this->launched);
    }

    public function testLongOutputIsCutAndHeartbeatIsSaved(): void
    {
        $task = $this->savedTask(['schedule' => '* * * * *']);
        $this->result['output'] = str_repeat('я', 60000);
        $now = mktime(10, 5, 0, 11, 23, 2026);

        $this->runner()->run($now);

        $output = (string) CronLog::findOne(['task_id' => $task->id])->output;
        self::assertLessThanOrEqual(CronRunner::OUTPUT_LIMIT + 200, strlen($output));
        self::assertStringContainsString('обрезан', $output);
        self::assertSame($now, CronRunner::heartbeat());
    }

    public function testLogCleansItselfByAgeAndByCount(): void
    {
        $task = $this->savedTask(['schedule' => '0 3 * * *']);
        $now = mktime(10, 5, 0, 11, 23, 2026);
        Yii::$app->settings->set('CRON', 'log_days', '7', 'integer');
        Yii::$app->settings->set('CRON', 'log_rows', '5', 'integer');
        $this->log($task, $now - 30 * 86400);
        for ($i = 1; $i <= 8; $i++) {
            $this->log($task, $now - $i * 3600);
        }

        $this->runner()->run($now);

        $left = CronLog::find()->where(['task_id' => $task->id])->orderBy(['started_at' => SORT_DESC])->all();
        self::assertCount(5, $left, 'Остаются последние записи в пределах лимита');
        self::assertSame($now - 3600, (int) $left[0]->started_at, 'Удаляются старые, а не свежие');
    }

    // --- Раздел в админке ---

    public function testSectionIsForSuperAdminOnly(): void
    {
        $this->loginAs((int) $this->createUser('editor', ['accessAdmin'])->id);

        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('admin/cron/index');
    }

    public function testPageShowsTasksAndSetupHintWhenDispatcherIsSilent(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->savedTask(['name' => 'Сброс кэша', 'schedule' => '0 3 * * *']);

        $html = $this->runAction('admin/cron/index');

        self::assertIsString($html);
        self::assertStringContainsString('Сброс кэша', $html);
        self::assertStringContainsString('каждый день в 03:00', $html);
        self::assertStringContainsString('admin/cron/run', $html, 'Нет строки для crontab');
    }

    public function testTaskIsCreatedToggledRunAndDeletedFromAdmin(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $this->runAction('admin/cron/create', [], ['CronTask' => [
            'name' => 'Переиндексация', 'command' => 'catalog/reindex', 'schedule' => '0 4 * * *', 'enabled' => '1', 'timeout' => '900',
        ]]);
        $task = CronTask::findOne(['name' => 'Переиндексация']);
        self::assertNotNull($task);

        $this->runAction('admin/cron/toggle', ['id' => $task->id], []);
        $task->refresh();
        self::assertSame(0, (int) $task->enabled);

        $this->runAction('admin/cron/run-now', ['id' => $task->id], []);
        $task->refresh();
        self::assertNotNull($task->run_requested_at);

        $this->runAction('admin/cron/delete', ['id' => $task->id], []);
        self::assertNull(CronTask::findOne($task->id));
        self::assertSame(0, (int) CronLog::find()->where(['task_id' => $task->id])->count());
    }

    public function testChangingActionsAcceptOnlyPost(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $task = $this->savedTask();

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->runAction('admin/cron/delete', ['id' => $task->id]);
    }

    // --- Помощники ---

    /**
     * @param array<string, mixed> $attributes
     */
    private function task(array $attributes = []): CronTask
    {
        $task = new CronTask();
        $task->setAttributes($attributes + [
            'name' => 'Задача', 'command' => 'cache/flush-all', 'schedule' => '* * * * *', 'enabled' => 1, 'timeout' => 600,
        ]);

        return $task;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function savedTask(array $attributes = []): CronTask
    {
        $task = $this->task($attributes);
        self::assertTrue($task->save(), print_r($task->getErrors(), true));

        return $task;
    }

    private function log(CronTask $task, int $startedAt): void
    {
        $log = new CronLog([
            'task_id' => $task->id, 'started_at' => $startedAt, 'finished_at' => $startedAt + 1,
            'duration_ms' => 1000, 'status' => 'ok', 'exit_code' => 0, 'output' => '', 'source' => 'schedule',
        ]);
        self::assertTrue($log->save(false));
    }

    private function runner(): CronRunner
    {
        return new CronRunner(function (array $jobs): array {
            $results = [];
            foreach ($jobs as $id => $job) {
                $this->launched[] = $job;
                $results[$id] = $this->result;
            }

            return $results;
        });
    }
}
