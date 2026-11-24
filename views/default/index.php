<?php
/** @var $this \yii\web\View */

use yii\helpers\Html;

$this->title = 'Панель управления';

\Mitisk\Yii2Admin\assets\IndexAsset::register($this);

$identity = Yii::$app->user->identity;
$name = $identity ? trim((string) ($identity->name ?: $identity->username)) : '';
$hour = (int) Yii::$app->formatter->asDate(time(), 'H');
$greeting = $hour < 5 ? 'Доброй ночи' : ($hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер'));
?>
<div class="db-hello">
    <div class="db-hello__text">
        <div class="db-hello__title"><?= $greeting ?><?= $name !== '' ? ', ' . Html::encode($name) : '' ?></div>
        <div class="db-hello__date"><?= Html::encode(Yii::$app->formatter->asDate(time(), 'EEEE, d MMMM')) ?></div>
    </div>
</div>
<?php

echo \Mitisk\Yii2Admin\widgets\IndexTopWidget::widget();

echo \Mitisk\Yii2Admin\widgets\IndexCenterWidget::widget();

echo \Mitisk\Yii2Admin\widgets\IndexAdminLogWidget::widget();
