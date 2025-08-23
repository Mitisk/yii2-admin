<?php
/* @var $this yii\web\View */
/** @var $field \Mitisk\Yii2Admin\fields\FileField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
/** @var $files string  */

\Mitisk\Yii2Admin\assets\FieldFileAsset::register($this);
?>

    <div class="body-title mb-10">
        <?= $field->label ?>
        <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
    </div>

    <input type="file" class="<?= $field->multiple ? "fileuploader-multiple" : "fileuploader-single" ?>"
           name="<?= \yii\helpers\Html::getInputName($model->getModel(), $field->name) ?>"
           data-fileuploader-files='<?php echo $files; ?>'>

<?= $this->render('_help_block', ['field' => $field]) ?>