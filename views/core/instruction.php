<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model Mitisk\Yii2Admin\models\AdminModelInfo */
/* @var $canEdit bool */
?>

<div class="model-info-view">
    <div class="content body-text">
        <?= $model->content ?>
    </div>
    
    <?php if ($canEdit): ?>
        <div class="mt-20">
            <?= Html::a('Редактировать', ['instruction', 'edit' => 1], ['class' => 'tf-button style-1']) ?>
        </div>
    <?php endif; ?>
</div>
