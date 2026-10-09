<?php
/** @var $field \Mitisk\Yii2Admin\fields\JsonField */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/** @var $fieldId string */

use yii\helpers\Html;

// Ace-редактор (тот же механизм, что у html-textarea): data-ace-mode + host.
\Mitisk\Yii2Admin\assets\AceAsset::register($this);
?>
    <div class="form-group">
        <label class="body-title mb-10" for="<?= $fieldId ?>">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <?= Html::activeTextarea($model->getModel(), $field->name, [
            'id' => $fieldId,
            'required' => $field->required,
            'readonly' => $field->readonly,
            'rows' => $field->rows,
            'autocomplete' => 'off',
            'data-ace-mode' => 'json',
        ]); ?>

        <div id="<?= $fieldId ?>__ace" class="ace-host"
             style="width:100%;height:<?= max(200, (int)$field->rows * 18) ?>px;
                    border:1px solid #e5e7eb;border-radius:6px;"></div>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>
