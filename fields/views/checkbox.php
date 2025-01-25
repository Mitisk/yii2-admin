<?php
/** @var $field \Mitisk\Yii2Admin\fields\TextField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
?>
    <div class="form-group flex gap10">

        <?= \yii\helpers\Html::activeCheckbox($model->getModel(), $field->name, [
            'id' => $fieldId,
            'required' => $field->required,
            'readonly' => $field->readonly,
            'autocomplete' => 'off',
            'label' => false
        ]); ?>

        <label for="<?= $fieldId ?>" class="body-text">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>