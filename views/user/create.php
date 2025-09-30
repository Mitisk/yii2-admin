<?php
/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $availableRoles array */
/* @var $assignedRoles array */

$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Добавить'];
$this->title = $this->params['pageHeaderText'] = 'Добавить пользователя';
?>

<?= $this->render('_form', ['model' => $model, 'assignedRoles' => $assignedRoles, 'availableRoles' => $availableRoles]) ?>
