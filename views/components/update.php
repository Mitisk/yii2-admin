<?php

use yii\helpers\Html;
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

$bundle = \Mitisk\Yii2Admin\assets\ComponentFormAsset::register($this);
$this->registerJsVar('i18nFormBuilderLocation', $bundle->baseUrl . '/component/form-builder/lang/', View::POS_END);
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
        <?= $form->field($model, 'alias', [
            'template' => '{label}<div class="input-group">{prefix}{input}{copy}</div>{error}',
            'parts' => [
                '{prefix}' => '<span class="input-group-text" style="font-size: 14px;padding-right: 0;">'
                    . Html::encode($host) . '/admin/</span>',
                '{copy}' => '<div class="box-coppy">'
                    . '<div class="coppy-content" style="display: none">'
                    . Html::encode($host) . '/admin/' . Html::encode($model->alias)
                    . '</div><i class="icon-copy button-coppy"></i></div>',
            ],
            'labelOptions' => ['class' => 'body-title mb-10'],
        ])->textInput([
            'maxlength' => 255,
            'class' => 'form-control',
            'style' => 'padding-left: 2px',
        ])->label('URL-адрес (Slug)');
        ?>
    </fieldset>

    <div class="flex gap10 mb-24">
        <?= Html::activeCheckbox($model, 'in_menu', ['class' => 'total-checkbox']) ?>
        <label for="adminmodel-in_menu" class="body-text">Добавить в меню слева. <a href="<?= \yii\helpers\Url::to(['/admin/menu/index']) ?>" target="_blank" class="tf-color">Редактировать в меню</a></label>
    </div>
    <fieldset class="name">
        <?= $form->field($model, 'model_class')->textInput([
            'maxlength' => 255,
            'placeholder' => 'Например: app\models\Product'
        ])->label('Путь к классу модели (Namespace)')
            ->hint('Укажите полный путь к Active Record классу, который будет управлять данными.') ?>
    </fieldset>
    <div class="block-warning type-main w-full">
        <i class="icon-alert-octagon"></i>
        <div class="body-title-2">Пример: app\models\Product.</div>
    </div>
</div>

<?php if ($model->model_class): ?>
    <div class="wg-box mb-20">
        <?php if ($allColumnsNames): ?>
            <h4>Настройка администрирования</h4>
            <div class="flex gap10 mb-24">
                <?= Html::activeCheckbox($model, 'can_create', ['class' => 'total-checkbox']) ?>
                <label for="<?= Html::getInputId($model, 'can_create') ?>" class="body-text">
                    Показывать кнопку «Добавить»
                </label>
            </div>

            <div class="flex gap10 mb-24">
                <?= Html::activeCheckbox($model, 'non_encode', ['class' => 'total-checkbox']) ?>
                <label for="<?= Html::getInputId($model, 'non_encode') ?>" class="body-text">
                    Разрешить HTML-код в ячейках
                </label>
            </div>

            <fieldset class="select">
                <?= $form->field($model, 'admin_label')
                    ->dropDownList(array_combine($allColumnsNames, $allColumnsNames), ['class' => 'tom-select'])
                    ->label('Основной заголовок записи')
                ?>
            </fieldset>
            <div class="block-warning type-main w-full">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Это поле, которое будет ссылкой на редактирование или отображаться в Select2 при связях.</div>
            </div>

        <?php endif; ?>
    </div>

    <div class="wg-box mb-20">
        <?php if ($allColumnsNames): ?>
            <h4>Настройка таблицы (Grid)</h4>

            <div class="wg-box-child mb-24 p-20">
                <h5 class="mb-14">Сортировка по умолчанию</h5>
                <div class="row" style="flex-wrap: wrap;">
                    <fieldset class="select flex-grow col-md-6">
                        <label class="body-title mb-10">По какому полю сортировать</label>
                        <div>
                        <?= $form->field($model, 'default_sort_attribute', ['template' => '{input}{error}'])
                            ->dropDownList(
                                \yii\helpers\ArrayHelper::merge([null => '--- По умолчанию (PK) ---'], array_combine($allColumnsNames, $allColumnsNames)),
                                ['class' => 'tom-select']
                            )
                        ?>
                        </div>
                    </fieldset>

                    <fieldset class="select flex-grow col-md-6">
                        <label class="body-title mb-10">Направление</label>
                        <?= $form->field($model, 'default_sort_direction', ['template' => '{input}{error}'])
                            ->dropDownList([
                                SORT_ASC => 'По возрастанию (А -> Я / 0 -> 9)',
                                SORT_DESC => 'По убыванию (Я -> А / 9 -> 0)',
                            ], ['class' => 'tom-select'])
                        ?>
                    </fieldset>
                </div>
                <div class="block-warning type-main w-full mt-5">
                    <i class="icon-alert-octagon"></i>
                    <div class="body-title-2">Если поле не выбрано, сортировка будет по Первичному ключу (ID) по убыванию.</div>
                </div>
            </div>

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
            <h4>Поля формы редактирования</h4>
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
