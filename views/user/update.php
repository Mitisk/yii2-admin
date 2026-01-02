<?php
/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $availableRoles array */
/* @var $assignedRoles array */
/* @var $maps \Mitisk\Yii2Admin\models\AdminUserMap[] */
/* @var $extraModels array */

$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Редактировать'];
$this->title = $this->params['pageHeaderText'] = 'Редактировать пользователя';
?>

<?= $this->render('_form', [
    'model' => $model,
    'assignedRoles' => $assignedRoles,
    'availableRoles' => $availableRoles,
    'maps' => $maps,
    'extraModels' => $extraModels
]) ?>
