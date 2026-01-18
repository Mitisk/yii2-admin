<?php
/** @var $field \Mitisk\Yii2Admin\fields\PostedField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */

use yii\helpers\Html;
?>
<div class="form-group flex gap10">
    <?= Html::activeCheckbox($model->getModel(), $field->name, [
        'label' => false,
        'id' => $fieldId,
        'autocomplete' => 'off',
        'value' => 1,
        'uncheck' => 0,
    ]) ?>
    <label for="<?= $fieldId ?>" class="body-text">
        <?php // Html::encode($field->label) ?>
    </label>
    <div class="col-lg-7 invalid-feedback"></div>
</div>
<?= $this->render('_help_block', ['field' => $field]) ?>
