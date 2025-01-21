<?php
/** @var $field \Mitisk\Yii2Admin\fields\TextField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
?>
<fieldset>
    <div class="form-group">
        <label class="body-title mb-10" for="<?= $fieldId ?>">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <?= \yii\helpers\Html::activeInput($field->subtype, $model->getModel(), $field->name, [
            'class' => $field->className,
            'placeholder' => $field->placeholder,
            'maxlength' => $field->maxlength,
            'id' => $fieldId,
            'required' => $field->required,
            'autocomplete' => 'off',
        ]); ?>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>
</fieldset>
<?php if ($field->description) { ?>
<div class="block-warning type-main w-full mb-24">
    <i class="icon-alert-octagon"></i>
    <div class="body-title-2"><?= $field->description ?></div>
</div>
<?php }?>