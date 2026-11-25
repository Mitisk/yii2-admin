<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\cron\CronExpression;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Задача планировщика: консольная команда проекта и расписание.
 *
 * Команда — маршрут консоли Yii и аргументы («catalog/reindex --force=1»), а не строка для shell:
 * диспетчер запускает её как `php yii <маршрут> <аргументы>` без командной оболочки.
 *
 * @property int $id
 * @property string $name
 * @property string $command
 * @property string $schedule
 * @property int $enabled
 * @property int $timeout
 * @property int|null $run_requested_at
 * @property int|null $running_since
 * @property int|null $last_run_at
 * @property string|null $last_status
 * @property int|null $last_duration_ms
 * @property int $created_at
 * @property int $updated_at
 */
class CronTask extends ActiveRecord
{
    public const STATUS_OK = 'ok';
    public const STATUS_FAIL = 'fail';
    public const STATUS_TIMEOUT = 'timeout';

    /** Маршрут консоли «контроллер/действие» (до четырёх частей) из строчных букв, цифр и дефисов */
    private const ROUTE_PATTERN = '~^[a-z0-9][a-z0-9-]*(/[a-z0-9][a-z0-9-]*){1,3}$~';
    /** Аргумент: без пробелов, кавычек и знаков командной оболочки */
    private const ARGUMENT_PATTERN = '~^[A-Za-z0-9_.,:=@/+-]+$~';

    public static function tableName(): string
    {
        return '{{%admin_cron_task}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name', 'command', 'schedule'], 'trim'],
            [['name', 'command', 'schedule'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['command'], 'string', 'max' => 500],
            [['schedule'], 'string', 'max' => 100],
            [['enabled'], 'boolean'],
            [['enabled'], 'default', 'value' => 1],
            [['timeout'], 'default', 'value' => 600],
            [['timeout'], 'integer', 'min' => 10, 'max' => 86400],
            [['command'], 'validateCommand'],
            [['schedule'], 'validateSchedule'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Название',
            'command' => 'Команда',
            'schedule' => 'Расписание',
            'enabled' => 'Включена',
            'timeout' => 'Лимит времени, секунд',
        ];
    }

    public function validateCommand(string $attribute): void
    {
        $parts = $this->commandParts();
        if ($parts === [] || !preg_match(self::ROUTE_PATTERN, $parts[0])) {
            $this->addError($attribute, 'Команда — это маршрут консоли Yii вида «контроллер/действие», например cache/flush-all или migrate/up. Произвольные команды системы не запускаются.');
            return;
        }
        if (str_starts_with($parts[0], 'admin/cron')) {
            $this->addError($attribute, 'Планировщик не запускает сам себя.');
            return;
        }
        foreach (array_slice($parts, 1) as $argument) {
            if (!preg_match(self::ARGUMENT_PATTERN, $argument)) {
                $this->addError($attribute, 'Аргумент «' . $argument . '» содержит недопустимые знаки. Разрешены буквы, цифры и . , : = @ / + - _');
                return;
            }
        }
    }

    public function validateSchedule(string $attribute): void
    {
        if (!CronExpression::isValid((string) $this->schedule)) {
            $this->addError($attribute, 'Расписание — пять полей cron: минута, час, день месяца, месяц, день недели. Например, «0 3 * * *» — каждый день в 03:00.');
        }
    }

    /**
     * Команда по частям: маршрут и аргументы.
     *
     * @return string[]
     */
    public function commandParts(): array
    {
        $parts = preg_split('/\s+/', trim((string) $this->command));

        return $parts === false ? [] : array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * «Запустить сейчас»: диспетчер выполнит задачу при ближайшем запуске, даже выключенную.
     */
    public function requestRun(): void
    {
        $this->updateAttributes(['run_requested_at' => time()]);
    }

    public function getLogs(): ActiveQuery
    {
        return $this->hasMany(CronLog::class, ['task_id' => 'id'])->orderBy(['started_at' => SORT_DESC, 'id' => SORT_DESC]);
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        CronLog::deleteAll(['task_id' => $this->id]);
    }
}
