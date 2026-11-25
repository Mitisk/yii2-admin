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
     *
     * Тихо, пока всё хорошо: печатает только догон пропущенных минут, пропуски и сбои (их же пишет в лог
     * приложения, категория cron). Код выхода 1 — хотя бы одна задача завершилась с ошибкой.
     */
    public function actionRun(): int
    {
        $summary = (new CronRunner())->run();
        foreach ($summary['problems'] as $line) {
            $this->stdout('admin/cron/run: ' . $line . "\n");
        }

        return $summary['failed'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
