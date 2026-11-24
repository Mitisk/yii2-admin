<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\components\DashboardWidgets;
use yii\base\Widget;

final class IndexTopWidget extends Widget
{
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $userId = (int) \Yii::$app->user->id;
        $widgets = DashboardWidgets::visible($userId);

        return $this->render('index/top', [
            'availableWidgets' => $widgets,
            'count' => count($widgets),
            'addable' => DashboardWidgets::addable($userId),
            'canAddByClass' => \Yii::$app->user->can('superAdminRole'),
        ]);
    }
}
