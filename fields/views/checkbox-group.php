<?php
/** @var $field \Mitisk\Yii2Admin\fields\TextField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
/** @var $values array  */

?>
    <div class="form-group">

        <label for="<?= $fieldId ?>" class="body-text">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <div class="active-checkbox-list">
            <?= \yii\helpers\Html::activeCheckboxList($model->getModel(), $field->name, $values, [
                'id' => $fieldId,
                'required' => $field->required,
                'readonly' => $field->readonly,
                'autocomplete' => 'off',
            ]); ?>

        </div>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>