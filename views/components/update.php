<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\widgets\ActiveForm;
use yii\web\View;

/* @var $model Mitisk\Yii2Admin\models\AdminModel */
/* @var $requiredColumns array */
/* @var $addedAttributes array */
/* @var $modelInstance yii\db\ActiveRecord|null */
/* @var $columnsNames array */
/* @var $allColumnsNames array */
/* @var $this yii\web\View */
/* @var $publicStaticMethods string */
/* @var $publicSaveMethods string */
/* @var $roles \yii\rbac\Role[] */

$this->title = 'Редактирование компонента';

$this->params['breadcrumbs'][] = ['label' => 'Компоненты', 'url' => ['index'] ];
$this->params['breadcrumbs'][] = $this->title;

\Mitisk\Yii2Admin\assets\ComponentFormAsset::register($this);
$host = Yii::$app->request->hostInfo;
?>

<?php $form = ActiveForm::begin([
    'id' => 'component-update-form',
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => ''],
        'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
    ],
    'options' => ['class' => 'flex flex-column gap24'],
]) ?>
<div class="wg-box mb-20">
    <fieldset class="name">
        <?= $form->field($model, 'name')->textInput(['maxlength' => 255])->label('Название <span class="tf-color-1">*</span>') ?>
    </fieldset>
    <fieldset class="name">
        <label class="body-title mb-10" for="adminmodel-alias">Алиас</label>
        <div class="input-group">
            <span class="input-group-text" style="font-size: 14px;padding-right: 0;"><?= Html::encode($host) ?>/admin/</span>
            <?= Html::activeInput('text', $model, 'alias', ['maxlength' => 255, 'class' => 'form-control', 'style' => 'padding-left: 2px'])?>
            <div class="box-coppy">
                <div class="coppy-content" style="display: none"><?= Html::encode($host) ?>/admin/<?= Html::encode($model->alias) ?></div>
                <i class="icon-copy button-coppy"></i>
            </div>
        </div>
    </fieldset>

    <div class="flex gap10 mb-24">
        <?= Html::activeCheckbox($model, 'in_menu', ['class' => 'total-checkbox']) ?>
        <label for="adminmodel-in_menu" class="body-text">Добавить в меню слева. <a href="/admin/menu" target="_blank" class="tf-color">Редактировать в меню</a></label>
    </div>
    <fieldset class="name">
        <?= $form->field($model, 'model_class')->textInput(['maxlength' => 255]) ?>
    </fieldset>
    <div class="block-warning type-main w-full">
        <i class="icon-alert-octagon"></i>
        <div class="body-title-2">Пример: app\models\ApiKey.</div>
    </div>
</div>

<?php if ($model->model_class): ?>
    <div class="wg-box mb-20">
        <?php if ($allColumnsNames): ?>
            <h4>Настройка администрирования</h4>
            <div class="flex gap10 mb-24">
                <?= Html::activeCheckbox($model, 'can_create', ['class' => 'total-checkbox']) ?>
                <label for="<?= Html::getInputId($model, 'can_create') ?>" class="body-text">
                    Разрешить создание новых записей из панели администратора.
                </label>
            </div>

            <div class="flex gap10 mb-24">
                <?= Html::activeCheckbox($model, 'non_encode', ['class' => 'total-checkbox']) ?>
                <label for="<?= Html::getInputId($model, 'non_encode') ?>" class="body-text">
                    Разрешить вывод данных без экранирования.
                </label>
            </div>

            <fieldset class="select">
                <?= $form->field($model, 'admin_label')->dropDownList(array_combine($allColumnsNames, $allColumnsNames), ['class' => 'tom-select']) ?>
            </fieldset>
        <?php endif; ?>
    </div>

    <div class="wg-box mb-20">
        <?php if ($allColumnsNames): ?>
            <h4>Столбцы в списке</h4>
            <div class="list-box-value mb-10 list-draggable-container">
                <?php foreach ((array)$model->list as $key => $column): ?>
                    <?php if (\yii\helpers\ArrayHelper::getValue($column, 'name')): ?>
                        <?= $this->render('_list_item', [
                            'model' => $model,
                            'column' => $key,
                            'requiredColumns' => $requiredColumns,
                            'name' => \yii\helpers\ArrayHelper::getValue($column, 'name'),
                            'description' => \yii\helpers\ArrayHelper::getValue($column, 'description'),
                        ]) ?>
                    <?php endif;?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="wg-box mb-20">
        <?php if ($allColumnsNames): ?>
            <h4>Столбцы в редакторе</h4>
            <div class="list-box-value mb-10">
                <?php foreach ($allColumnsNames as $column): ?>
                    <div class="box-value-item" style="gap: 10px;justify-content: left;">
                        <input class="total-checkbox js-click-to-attr" type="checkbox"
                               data-type="<?= \Mitisk\Yii2Admin\fields\FieldsHelper::getFieldsTypeByName($column) ?>"
                               data-name="<?= Html::encode($column) ?>"
                               data-label="<?= Html::encode($modelInstance->getAttributeLabel($column)) ?>"
                               data-required="<?= in_array($column, $requiredColumns, true) ? 'true' : 'false' ?>"
                            <?= in_array($column, $addedAttributes, true) ? 'checked disabled' : ''?>>
                        <div class="body-text">
                            <?= Html::encode($modelInstance->getAttributeLabel($column)) ?><?= in_array($column, $requiredColumns, true) ? ' <span class="tf-color-1">*</span>' : '' ?>
                            <span class="block-pending"><?= Html::encode($column) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="build-wrap"></div>
        <?php endif; ?>
        <?= $form->field($model, 'data')->hiddenInput()->label(false) ?>
    </div>
<?php endif; ?>

<div class="bot">
    <div></div>
    <button class="tf-button w208 js-check-to-save" type="submit"><?= ($model->model_class) ? 'Сохранить' : 'Продолжить'?></button>
</div>

<?php ActiveForm::end() ?>

<?php
// Безопасная передача данных в JS
$this->registerJsVar('formData', $model->data ?: '[]', View::POS_END);
$this->registerJsVar('publicStaticMethods', Json::decode($publicStaticMethods) ?: [], View::POS_END);
$this->registerJsVar('publicSaveMethods', Json::decode($publicSaveMethods) ?: [], View::POS_END);
$this->registerJsVar('roles', array_reduce($roles, static function($acc, $role) { $acc[$role->name] = $role->description; return $acc; }, []), View::POS_END);
?>
