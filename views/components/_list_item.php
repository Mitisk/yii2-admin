<?php
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/* @var $model Mitisk\Yii2Admin\models\AdminModel */
/* @var $column string */
/* @var $name string */
/* @var $description string */
/* @var $requiredColumns array */

?>

<div class="box-value-item list-draggable">
    <input class="total-checkbox" name="<?= Html::getInputName($model, 'list') ?>[<?= $column ?>][on]" value="1" type="checkbox"
        <?= ArrayHelper::getValue($model->list, $column.'.on') ? 'checked' : '' ?>>
    <?= Html::hiddenInput(Html::getInputName($model, 'list').'[' . $column . '][ordering]', '1') ?>
    <div class="body-text">
        <?= $name ?><?= in_array($column, $requiredColumns) ? ' <span class="tf-color-1">*</span>' : '' ?>
        <span class="block-pending"><?= $description ?></span>
    </div>
    <div class="drag-area"><a class="grid-button btn formbuilder-icon-grid" title="Переместить"></a></div>
</div>
