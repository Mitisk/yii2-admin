<?php

use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use yii\web\View;
use Mitisk\Yii2Admin\fields\FieldsHelper;

/* @var $this yii\web\View */
/* @var $model Mitisk\Yii2Admin\models\AdminModel */
/* @var $requiredColumns array */
/* @var $addedAttributes array */
/* @var $modelInstance yii\db\ActiveRecord|null */
/* @var $columnsNames array */
/* @var $allColumnsNames array */
/* @var $publicStaticMethods string */
/* @var $publicSaveMethods string */
/* @var $roles \yii\rbac\Role[] */

$this->title = 'Редактирование компонента';
$this->params['breadcrumbs'][] = ['label' => 'Компоненты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$bundle = \Mitisk\Yii2Admin\assets\ComponentFormAsset::register($this);
$this->registerJsVar('i18nFormBuilderLocation', $bundle->baseUrl . '/component/form-builder/lang/', View::POS_END);

// SortableJS для визуального холста
$this->registerJsFile(
    'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
    ['position' => View::POS_HEAD]
);

$host = Yii::$app->request->hostInfo;

$effectiveLabels = [];
$dropdownItems   = [];
if ($allColumnsNames && $modelInstance) {
    foreach ($allColumnsNames as $name) {
        $label = isset($model->attribute_labels[$name]) && $model->attribute_labels[$name] !== ''
            ? $model->attribute_labels[$name]
            : $modelInstance->getAttributeLabel($name);
        $effectiveLabels[$name] = $label;
        $dropdownItems[$name]   = $label . ' (' . $name . ')';
    }
}

// Данные полей для JS-холста
$publicPropsSet       = array_flip($publicProps ?? []);
$allDbAttributesForJs = [];
$allPublicAttributesForJs = [];
if ($allColumnsNames) {
    foreach ($allColumnsNames as $col) {
        $entry = [
            'name'     => $col,
            'label'    => $effectiveLabels[$col] ?? $col,
            'type'     => FieldsHelper::getFieldsTypeByName($col),
            'required' => in_array($col, $requiredColumns, true),
        ];
        if (isset($publicPropsSet[$col])) {
            $allPublicAttributesForJs[] = $entry;
        } else {
            $allDbAttributesForJs[] = $entry;
        }
    }
}

// ─── CSS ─────────────────────────────────────────────────
require __DIR__ . '/_update_css.php';

// ─── Форма ───────────────────────────────────────────────
$form = ActiveForm::begin(
    [
        'id'          => 'component-update-form',
        'fieldConfig' => [
            'template'     => "{label}\n{input}\n{error}",
            'labelOptions' => ['class' => 'body-title mb-10'],
            'inputOptions' => ['class' => ''],
            'errorOptions' => ['class' => 'invalid-feedback'],
        ],
        'options' => ['class' => 'flex flex-column gap24'],
    ]
) ?>

<?php if (!$model->model_class || ($model->model_class && $model->model_class == 'app\models\\')) : ?>
    <?php /* ШАГ 1: Базовая настройка (welcome card) */ ?>
    <?php include __DIR__ . '/_update_welcome.php' ?>

<?php else : ?>
    <?php /* ШАГ 2: Полный редактор (табы) */ ?>
    <?php include __DIR__ . '/_update_editor.php' ?>

<?php endif; ?>

<?php ActiveForm::end() ?>

<?php require __DIR__ . '/_update_links_js.php' ?>
<?php require __DIR__ . '/_update_link_slots_js.php' ?>
<?php require __DIR__ . '/_update_canvas_js.php' ?>
