<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\AdminUser;
use yii\helpers\Url;

/**
 * Карточка главной «Пользователи»: всего, новых за неделю, сколько в сети и заблокировано.
 */
final class IndexUserWidget extends StatWidget
{
    /** Сколько секунд после последнего запроса пользователь считается «в сети» */
    private const ONLINE_WINDOW = 300;

    public $permission = 'viewUsers';

    /** Название в списке «Добавить виджет» на главной. */
    public static function dashboardTitle(): string
    {
        return 'Пользователи';
    }

    protected function stat(): array
    {
        $now = time();
        $total = (int) AdminUser::find()->count();
        $blocked = (int) AdminUser::find()->where(['status' => AdminUser::STATUS_BLOCKED])->count();
        $online = (int) AdminUser::find()->where(['>=', 'online_at', $now - self::ONLINE_WINDOW])->count();

        // Регистрации по дням за неделю: один запрос, раскладка по дням — в PHP
        $dayStart = strtotime('today', $now);
        $byDay = array_fill(0, 7, 0);
        $created = AdminUser::find()
            ->select('created_at')
            ->where(['>=', 'created_at', $dayStart - 6 * 86400])
            ->column();
        foreach ($created as $time) {
            $index = 6 - (int) floor(($dayStart + 86400 - 1 - (int) $time) / 86400);
            if ($index >= 0 && $index <= 6) {
                $byDay[$index]++;
            }
        }
        $week = array_sum($byDay);

        $notes = ['в сети: ' . $online];
        if ($blocked > 0) {
            $notes[] = 'заблокировано: ' . $blocked;
        }

        return [
            'title' => 'Пользователи',
            'icon' => 'icon-users',
            'value' => $total,
            'trend' => $week > 0 ? '+' . $week . ' за неделю' : '',
            'note' => implode(' · ', $notes),
            'spark' => $byDay,
            'tone' => 'blue',
            'url' => Url::to(['/admin/user/index']),
        ];
    }
}
