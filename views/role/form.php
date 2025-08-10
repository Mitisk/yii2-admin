<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;

/** @var $role \app\modules\rbac\models\Role */
?>

<div class="col-12 mb-20">
    <div class="wg-box">
        <div class="row">
            <div class="col-12 mb-20">
                <div>
                    <?php $form = ActiveForm::begin([
                        'id' => 'login-form',
                        'fieldConfig' => [
                            'template' => "{label}\n{input}\n{error}",
                            'labelOptions' => ['class' => 'body-title mb-10'],
                            'inputOptions' => ['class' => ''],
                            'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
                        ],
                        'options' => ['class' => 'form-login flex flex-column gap24']
                    ]) ?>


                        <fieldset class="name mb-24">
                            <?= $form->field($model, 'name')->textInput(['maxlength' => 64])->label('Имя <span class="tf-color-1">*</span>') ?>
                        </fieldset>

                        <fieldset class="ruleName mb-24">
                            <?= $form->field($model, 'ruleName')->dropDownList(ArrayHelper::map(Yii::$app->authManager->getRules(), 'name', 'name'), ['class' => 'tom-select',]) ?>

                        </fieldset>
                        <fieldset class="description mb-24">
                            <?= $form->field($model, 'description')->textarea(['rows' => 2]) ?>
                        </fieldset>
                        <fieldset class="description mb-24">
                            <?= $form->field($model, 'data')->textarea(['rows' => 6]) ?>
                        </fieldset>

                        <div class="bot">
                            <div class="list-box-value mb-10">
                                <div>
                                    <?= Html::submitButton($model->isNewRecord
                                        ? Yii::t('rbac', 'Create')
                                        : Yii::t('rbac', 'Update role'), [
                                        'class' => $model->isNewRecord
                                            ? 'tf-button w208'
                                            : 'tf-button w208'
                                    ]) ?>
                                </div>
                                <div>
                                    <?= !$model->isNewRecord ? Html::a(Yii::t('rbac', 'Delete'), ['delete', 'id' => $model->name], [
                                        'class' => 'tf-button tf-button-danger w208',
                                        'data-confirm' => Yii::t('rbac', 'Are you sure to delete this item?'),
                                        'data-method' => 'post',
                                    ]) : '' ?>
                                </div>
                            </div>



                        </div>
                    <?php ActiveForm::end() ?>
                </div>
            </div>
        </div>
    </div>
</div>