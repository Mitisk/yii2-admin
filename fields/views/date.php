<?php
/** @var $field \Mitisk\Yii2Admin\fields\DateField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
?>
<div class="form-group">
    <label class="body-title mb-10" for="<?= $fieldId ?>">
        <?= $field->label ?>
        <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
    </label>

    <?= \yii\helpers\Html::activeInput('date', $model->getModel(), $field->name, [
        'class' => $field->className,
        'placeholder' => $field->placeholder,
        'id' => $fieldId,
        'required' => $field->required,
        'readonly' => $field->readonly,
        'autocomplete' => 'off',
    ]); ?>

</div>

<?= $this->render('_help_block', ['field' => $field]) ?>