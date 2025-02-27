<?php
/* @var $this yii\web\View */
/** @var $field \Mitisk\Yii2Admin\fields\FileField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
/** @var $files string  */

$this->registerJsFile('/web/component/fileuploader/dist/jquery.fileuploader.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('/web/component/fileuploader/js/custom.js', ['depends' => [\yii\web\JqueryAsset::class]]);

$this->registerCssFile('/web/component/fileuploader/dist/jquery.fileuploader.min.css');
$this->registerCssFile('/web/component/fileuploader/dist/font/font-fileuploader.css');
$this->registerCssFile('/web/component/fileuploader/css/jquery.fileuploader-theme-thumbnails.css');

$this->registerCss('.fileuploader {max-width: 1200px;}');
$this->registerCss('.alt-fileuploader-input {background-color: white !important;padding: 4px 12px !important;}');
?>
<div class="body-title mb-10">
    <?= $field->label ?>
    <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
</div>

<input type="file" class="<?= $field->multiple ? "fileuploader-multiple" : "fileuploader-single" ?>"
       name="<?= \yii\helpers\Html::getInputName($model->getModel(), $field->name) ?>"
       data-fileuploader-files='<?php echo $files; ?>'>

<?= $this->render('_help_block', ['field' => $field]) ?>