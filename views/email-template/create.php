<?php
/* @var $this yii\web\View */
/* @var $model Mitisk\Yii2Admin\models\EmailTemplate */

$this->title = 'Создание шаблона';
$this->params['breadcrumbs'][] = ['label' => 'Шаблоны писем', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

    <?= $this->render('_form', ['model' => $model]) ?>
