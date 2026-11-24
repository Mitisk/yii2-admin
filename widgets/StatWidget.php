<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\widgets;

use Yii;
use yii\base\Widget;

/**
 * Карточка статистики в верхнем ряду дашборда (/admin/). Подкласс отдаёт данные в stat(),
 * вёрстка и меню карточки («Переместить», «Скрыть») — общие, из пакета.
 *
 * ```php
 * final class OrdersTodayWidget extends StatWidget
 * {
 *     protected function stat(): array
 *     {
 *         return [
 *             'title' => 'Заказов сегодня', 'icon' => 'icon-shopping-bag',
 *             'value' => Order::find()->where(['>=', 'created_at', strtotime('today')])->count(),
 *             'note' => 'за неделю: ' . $week, 'url' => '/admin/orders/',
 *         ];
 *     }
 * }
 * ```
 * Регистрация — AdminRegistry::registerDashboardWidget('OrdersTodayWidget', OrdersTodayWidget::class, 3).
 */
abstract class StatWidget extends Widget
{
    /** Окно «за период» по умолчанию для since(), секунд (7 дней). */
    protected const PERIOD = 7 * 86400;

    /**
     * Кто видит карточку: роль/разрешение или список (достаточно одного); null — любой вошедший.
     *
     * @var string|string[]|null
     */
    public $permission = 'admin';

    /**
     * Данные карточки: title, icon (класс иконки темы, например icon-users), value (число —
     * с разделителем тысяч), необязательные note (строка под числом) и url (вся карточка — ссылка).
     *
     * @return array{title: string, icon: string, value: int|float|string, note?: string, url?: string}
     */
    abstract protected function stat(): array;

    /** Начало окна статистики, unix time. */
    protected function since(): int
    {
        return time() - static::PERIOD;
    }

    public function run(): string
    {
        if (!$this->isVisible()) {
            return '';
        }

        $stat = $this->stat();
        $value = $stat['value'] ?? '';
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $value = number_format((float)$value, 0, '.', ' ');
        }

        return $this->getView()->render('@Mitisk/Yii2Admin/widgets/views/index/stat.php', [
            'name' => (new \ReflectionClass($this))->getShortName(),
            'title' => (string)($stat['title'] ?? ''),
            'icon' => (string)($stat['icon'] ?? 'icon-activity'),
            'value' => (string)$value,
            'note' => (string)($stat['note'] ?? ''),
            'url' => (string)($stat['url'] ?? ''),
        ], $this);
    }

    private function isVisible(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        if ($this->permission === null) {
            return true;
        }
        foreach ((array)$this->permission as $item) {
            if (Yii::$app->user->can($item)) {
                return true;
            }
        }
        return false;
    }
}
