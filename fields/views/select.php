<?php
/** @var $field \Mitisk\Yii2Admin\fields\TextField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
/** @var $values array  */
/** @var $selected array  */

\Mitisk\Yii2Admin\assets\FieldSelectAsset::register($this);
?>
    <div class="form-group">

        <label for="<?= $fieldId ?>" class="body-title mb-10">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <div class="active-checkbox-list">
            <?php if ($selected) : ?>
                <?= \yii\helpers\Html::dropDownList(
                    \yii\helpers\Html::getInputName($model->getModel(), $field->name),
                    $selected,
                    $values,
                    [
                        'id' => $fieldId,
                        'required' => $field->required,
                        'readonly' => $field->readonly,
                        'multiple' => $field->multiple,
                        'class' => 'tom-select',
                        'data-raw-select' => $field->multiple ? 'true' : false,
                        'autocomplete' => 'off'
                    ]); ?>
            <?php else : ?>
                <?= \yii\helpers\Html::activeDropDownList($model->getModel(), $field->name, $values, [
                    'id' => $fieldId,
                    'required' => $field->required,
                    'readonly' => $field->readonly,
                    'multiple' => $field->multiple,
                    'class' => 'tom-select',
                    'data-raw-select' => $field->multiple ? 'true' : false,
                    'autocomplete' => 'off'
                ]); ?>
            <?php endif; ?>

        </div>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>
