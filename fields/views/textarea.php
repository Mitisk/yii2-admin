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
        'autocomplete' => 'off',
        'rows' => $field->rows,
    ]); ?>

    <div class="col-lg-7 invalid-feedback"></div>
</div>

<?= $this->render('_help_block', ['field' => $field]) ?>

<?php if ($field->subtype == 'tinymce') {
    $this->registerJsFile('https://cdn.tiny.cloud/1/no-api-key/tinymce/7/tinymce.min.js', ['position' => \yii\web\View::POS_END]);
    $this->registerJs('tinymce.init({
    selector: "#' . $fieldId . '",
    });
    ');
}

if($field->subtype == 'quill') {
    $this->registerJsFile('https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js', ['position' => \yii\web\View::POS_END]);
    $this->registerCssFile('https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css');
    $this->registerJs('
    var quill_' . str_replace('-', '_', $fieldId) . ' = new Quill("#' . $fieldId . '", {
        theme: "snow"
    });
    ');
}

/*
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />

<!-- Create the editor container -->
<div id="editor">
    <p>Hello World!</p>
    <p>Some initial <strong>bold</strong> text</p>
    <p><br /></p>
</div>

<!-- Include the Quill library -->
<script src=""></script>

<!-- Initialize Quill editor -->
<script>
    const quill = new Quill('#editor', {
        theme: 'snow'
    });
</script>*/