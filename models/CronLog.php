<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use yii\db\ActiveRecord;

/**
 * Запись журнала планировщика: один запуск задачи. Журнал чистит сам диспетчер (CronRunner::cleanLog()).
 *
 * @property int $id
 * @property int $task_id
 * @property int $started_at
 * @property int|null $finished_at
 * @property int|null $duration_ms
 * @property string $status ok | fail | timeout
 * @property int|null $exit_code
 * @property string $source schedule | manual
 * @property string|null $output
 */
class CronLog extends ActiveRecord
{
    public const SOURCE_SCHEDULE = 'schedule';
    public const SOURCE_MANUAL = 'manual';

    public static function tableName(): string
    {
        return '{{%admin_cron_log}}';
    }
}
