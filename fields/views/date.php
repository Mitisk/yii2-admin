<?php
/**
 * Date / datetime-local field view.
 *
 * @category View
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: $Id$
 * @link     https://github.com/mitisk/yii2-admin
 * @php      8.0
 */

/* @var $field \Mitisk\Yii2Admin\fields\DateField */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/* @var $fieldId string */
/* @var $rawValue mixed */
/* @var $formattedValue string */
/* @var $inputType string */
/* @var $isTimestamp bool */

use yii\helpers\Html;
?>
<div class="form-group">
    <label class="body-title mb-10" for="<?php echo $fieldId ?>_picker">
        <?php echo Html::encode($field->label) ?>
        <?php if ($field->required) { ?>
            <span class="tf-color-1">*</span>
        <?php } ?>
    </label>

    <?php /* Hidden input carries the value the model receives on save */ ?>
    <?php echo Html::hiddenInput(
        Html::getInputName($model->getModel(), $field->name),
        $rawValue,
        ['id' => $fieldId]
    ) ?>

    <input type="<?php echo Html::encode($inputType) ?>"
           id="<?php echo $fieldId ?>_picker"
           class="form-control <?php echo Html::encode($field->className ?? '') ?>"
           value="<?php echo Html::encode($formattedValue) ?>"
           <?php if ($field->min) { ?>
               min="<?php echo Html::encode($field->min) ?>"
           <?php } ?>
           <?php if ($field->max) { ?>
               max="<?php echo Html::encode($field->max) ?>"
           <?php } ?>
           <?php if ($field->step) { ?>
               step="<?php echo Html::encode($field->step) ?>"
           <?php } ?>
           <?php echo $field->required ? 'required' : '' ?>
           <?php echo $field->readonly ? 'readonly' : '' ?>
           autocomplete="off"
           data-hidden="<?php echo $fieldId ?>"
           data-is-ts="<?php echo $isTimestamp ? '1' : '0' ?>">
</div>

<?php echo $this->render('_help_block', ['field' => $field]) ?>

<?php
$this->registerJs(
    <<<'JS'
(function () {
    document.querySelectorAll('input[data-hidden]').forEach(function (picker) {
        var hidden = document.getElementById(picker.dataset.hidden);
        if (!hidden) return;
        var isTs = picker.dataset.isTs === '1';

        function syncToHidden() {
            var v = picker.value;
            if (!v) { hidden.value = ''; return; }
            if (isTs) {
                var p = v.split(/[-T:]/);
                var d = new Date(
                    +p[0], +p[1] - 1, +p[2],
                    p[3] !== undefined ? +p[3] : 0,
                    p[4] !== undefined ? +p[4] : 0,
                    p[5] !== undefined ? +p[5] : 0
                );
                hidden.value = Math.floor(d.getTime() / 1000);
            } else {
                hidden.value = v;
            }
        }

        picker.addEventListener('change', syncToHidden);

        var form = picker.closest('form');
        if (form) {
            form.addEventListener('submit', syncToHidden);
        }
    });
}());
JS
);
?>
