<?php
/** @var $field \Mitisk\Yii2Admin\fields\TextField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
?>
<div class="form-group">
    <label class="body-title mb-10" for="<?= $fieldId ?>">
        <?= $field->label ?>
        <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
    </label>

    <?= \yii\helpers\Html::activeTextarea($model->getModel(), $field->name, [
        'placeholder' => $field->placeholder,
        'maxlength' => $field->maxlength,
        'id' => $fieldId,
        'required' => $field->required,
        'readonly' => $field->readonly,
        'autocomplete' => 'off',
        'rows' => $field->rows,
    ]); ?>

    <div class="col-lg-7 invalid-feedback"></div>
</div>

<?= $this->render('_help_block', ['field' => $field]) ?>

<?php if ($field->viewtype == 'visual') {
    $this->registerJsFile('https://cdn.tiny.cloud/1/no-api-key/tinymce/7/tinymce.min.js', ['position' => \yii\web\View::POS_END]);
    $this->registerJs('tinymce.init({
    selector: "#' . $fieldId . '",
    });
    ');
}
if ($field->viewtype == 'html') {
    /** @todo Добавить редактор кода */
}
