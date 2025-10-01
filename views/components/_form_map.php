<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminControllerMap */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="admin-controller-map-form">

    <?php $form = ActiveForm::begin([
        'fieldConfig' => [
            'template' => "{label}\n{input}\n{error}",
            'labelOptions' => ['class' => 'body-title mb-10'],
            'inputOptions' => ['class' => ''],
            'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
        ],
        'options' => ['class' => 'flex flex-column gap24'],
    ]) ?>

    <?= $form->field($model, 'controller_id')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'class')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'config')->textarea(['rows' => 8])
        ->hint('Опционально: валидный JSON с дополнительной конфигурацией контроллера') ?>

    <?= $form->field($model, 'enabled')->checkbox() ?>

    <div class="bot">
        <div class="list-box-value mb-10">
            <div>
                <?= Html::submitButton($model->isNewRecord
                    ? "Добавить"
                    : "Обновить", [
                    'class' => $model->isNewRecord
                        ? 'tf-button w208'
                        : 'tf-button w208'
                ]) ?>
            </div>
            <div>
                <?= !$model->isNewRecord ? Html::a(Yii::t('rbac', 'Delete'), ['delete-map', 'id' => $model->id], [
                    'class' => 'tf-button tf-button-danger w208',
                    'data-confirm' => Yii::t('rbac', 'Are you sure to delete this item?'),
                    'data-method' => 'post',
                ]) : '' ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>

</div>