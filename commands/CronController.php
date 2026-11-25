<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\commands;

use Mitisk\Yii2Admin\components\cron\CronRunner;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Диспетчер планировщика задач админки.
 *
 * В crontab сервера — одна строка, расписание самих задач задаётся в /admin/cron/:
 *
 * ```
 * * * * * * cd /path/to/project && php yii admin/cron/run >/dev/null 2>&1
 * ```
 *
 * Нужен модуль admin в консольном конфиге (bootstrap + modules), как для `php yii admin/update`.
 */
class CronController extends Controller
{
    /**
     * Запустить задачи, которым пора, и почистить журнал.
     */
    public function actionRun(): int
    {
        $summary = (new CronRunner())->run();
        if ($summary['started'] > 0) {
            $this->stdout(sprintf("Запущено задач: %d, успешно: %d, с ошибкой: %d\n", $summary['started'], $summary['ok'], $summary['failed']));
        }

        return ExitCode::OK;
    }
}
