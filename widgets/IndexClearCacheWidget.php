<?php

namespace Mitisk\Yii2Admin\widgets;

use yii\base\Widget;

final class IndexClearCacheWidget extends Widget
{
    /**
     * {@inheritdoc}
     */
    /** Название в списке «Добавить виджет» на главной. */
    public static function dashboardTitle(): string
    {
        return 'Очистка кэша сайта';
    }

    public function run()
    {
        if (!\Yii::$app->user->can('manageSystem')) {
            return '';
        }

        return $this->render('index/clear-cache');
    }
}
