<?php
/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */

$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
//$this->params['breadcrumbs'][] = ['label' => \yii\helpers\Html::encode($model->name), 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = ['label' => 'Редактировать'];
$this->title = $this->params['pageHeaderText'] = 'Редактировать пользователя';
?>

<?= $this->render('_form', ['model' => $model]) ?>
