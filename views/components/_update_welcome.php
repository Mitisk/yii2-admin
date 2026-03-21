<?php
/* @var $this yii\web\View */
/* @var $model Mitisk\Yii2Admin\models\AdminModel */
/* @var $form yii\widgets\ActiveForm */
/* @var $host string */

use yii\helpers\Html;
?>
<div class="comp-welcome-outer">
    <div class="comp-welcome-card">

        <div class="comp-welcome-header">
            <div class="comp-stepper">
                <div class="comp-step active">
                    <div class="comp-step-num">1</div>
                    <span>Базовая настройка</span>
                </div>
                <div class="comp-step-divider"></div>
                <div class="comp-step">
                    <div class="comp-step-num">2</div>
                    <span>Визуальный конструктор</span>
                </div>
            </div>
            <div class="icon-wrapper">
                <i class="fas fa-magic"></i>
            </div>
            <h3 class="mb-6" style="font-size:22px;font-weight:700;color: white;">Новый компонент</h3>
            <p class="mb-0" style="color:rgba(255,255,255,.6);font-size:14px;">
                Укажите основные данные и привяжите класс модели Yii2
            </p>
        </div>

        <div class="comp-welcome-body">

            <div class="row" style="flex-wrap:wrap;gap:0;">
                <fieldset class="col-md-6 name pe-md-2 mb-20">
                    <?= $form->field($model, 'name')
                        ->textInput(['maxlength' => 255, 'autocomplete' => 'off', 'autofocus' => true])
                        ->label('Название <span class="tf-color-1">*</span>') ?>
                </fieldset>
                <fieldset class="col-md-6 name ps-md-2 mb-20">
                    <?= $form->field($model, 'alias', [
                        'template' => '{label}<div class="input-group">{prefix}{input}{copy}</div>{error}',
                        'parts'    => [
                            '{prefix}' => '<span class="input-group-text" style="font-size:13px;padding-right:0;">'
                                . Html::encode($host) . '/admin/</span>',
                            '{copy}'   => '<div class="box-coppy">'
                                . '<div class="coppy-content" style="display:none">'
                                . Html::encode($host) . '/admin/' . Html::encode($model->alias)
                                . '</div><i class="icon-copy button-coppy"></i></div>',
                        ],
                        'labelOptions' => ['class' => 'body-title mb-10'],
                    ])->textInput([
                        'maxlength' => 255,
                        'autocomplete' => 'off',
                        'class'     => 'form-control',
                        'style'     => 'padding-left:2px',
                    ])->label('URL-адрес (Slug)') ?>
                </fieldset>
            </div>

            <div class="flex gap10 mb-24">
                <?= Html::activeCheckbox($model, 'in_menu', ['class' => 'total-checkbox']) ?>
                <label for="adminmodel-in_menu" class="body-text">
                    Добавить в меню слева.
                </label>
            </div>

            <fieldset class="name mb-16">
                <?= $form->field($model, 'model_class')->textInput([
                    'maxlength'   => 255,
                    'placeholder' => 'Например: app\models\Product',
                    'style'       => 'font-family:monospace;font-size:16px;',
                    'autocomplete' => 'off',
                ])->label('Путь к классу модели (Namespace)')
                ->hint('<i class="fas fa-info-circle me-1"></i> Система проанализирует класс, чтобы получить список полей БД и свойств для конструктора.') ?>
            </fieldset>

            <div class="block-warning type-main w-full mb-24">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Пример: <code>app\models\Product</code></div>
            </div>

            <button class="tf-button w-100 js-check-to-save" type="submit">
                Продолжить и настроить поля <i class="fas fa-arrow-right ms-2"></i>
            </button>

        </div>
    </div>
</div>
