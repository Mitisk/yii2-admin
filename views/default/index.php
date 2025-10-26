<?php
/** @var $this \yii\web\View */

$this->title = 'Панель управления';

\Mitisk\Yii2Admin\assets\IndexAsset::register($this);

echo \Mitisk\Yii2Admin\widgets\IndexTopWidget::widget();

echo \Mitisk\Yii2Admin\widgets\IndexCenterWidget::widget();

echo \Mitisk\Yii2Admin\widgets\IndexAdminLogWidget::widget();
