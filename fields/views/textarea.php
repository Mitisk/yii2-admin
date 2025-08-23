<?php
/** @var $field \Mitisk\Yii2Admin\fields\TextField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */

if ($field->viewtype == 'visual') {
    \Mitisk\Yii2Admin\assets\TrumbowygAsset::register($this);
} elseif ($field->viewtype == 'html') {
    \Mitisk\Yii2Admin\assets\AceAsset::register($this);
}
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
            // прокинем желаемый режим подсветки (если нужно): text|html|css|javascript|php|sql|markdown ...
            'data-ace-mode' => $field->viewtype == 'html' ? 'html' : false,
        ]); ?>

        <?php if ($field->viewtype == 'html') : ?>
            <!-- Контейнер для Ace. Высота настраивается стилем/классом -->
            <div id="<?= $fieldId ?>__ace" class="ace-host" style="width:100%;height:<?= max(200, (int)$field->rows * 18) ?>px;border:1px solid #e5e7eb;border-radius:6px;">

            </div>
        <?php endif; ?>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>

<?php if ($field->viewtype == 'visual') {
    $this->registerJs('
        $(\'#' . $fieldId . '\').trumbowyg({
            lang: "ru",
            imageWidthModalEdit: true,
            autogrow: true,
            btns: [
                ["undo", "redo"],
                ["removeformat"],
                ["justifyLeft", "justifyCenter", "justifyRight", "justifyFull"],
                ["unorderedList", "orderedList"],
                ["table"],
                ["emoji"],
                ["link", "insertImage"],
                ["formatting"],
                ["strong", "em", "del"],
                ["superscript", "subscript"],
                ["horizontalRule"],
                ["viewHTML"],
                
                ["fullscreen"]
            ]
        });
    ');
}