<?php
/** @var $field \Mitisk\Yii2Admin\fields\TextField  */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel  */
/** @var $fieldId string  */
/** @var $values array  */
/** @var $selected array  */
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
                        'autocomplete' => 'off'
                    ]); ?>
            <?php else : ?>
                <?= \yii\helpers\Html::activeDropDownList($model->getModel(), $field->name, $values, [
                    'id' => $fieldId,
                    'required' => $field->required,
                    'readonly' => $field->readonly,
                    'multiple' => $field->multiple,
                    'autocomplete' => 'off'
                ]); ?>
            <?php endif; ?>

        </div>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>


<script>
    /*$(document).ready(function() {
        $('select[multiple]').each(function() {
            $(this).chosen();
        });
    });*/
</script>
<?php $this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);?>
<?php
$this->registerJs(<<<JS
$('select[multiple]').each(function() {
            $(this).chosen({
                // This option restricts the number
                // of items for selection
                max_selected_options: 15,
                // This option keeps the dropdown 
                  // open till the selection
                hide_results_on_select: true
            });
        });
JS);
?>

<!-- CDN for CSS of chosen plugin -->
<link rel="stylesheet" href=
"https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer" />

<style type="text/css">
    .chosen-container-multi .chosen-choices li.search-choice {
        background: #e6ebf4;
        border: none;
        padding: 8px 20px 8px 10px;
    }
    .chosen-container-multi .chosen-choices li.search-choice .search-choice-close {
        top: 8px;
    }
    .chosen-container-multi .chosen-choices {
        border: 1px solid var(--Input);
        border-radius: 12px;
        box-shadow: none;
        background: #fff;
        padding: 6px 22px;
    }
    .chosen-container .chosen-drop {
        border: none;
    }

    .chosen-container .chosen-results li.highlighted {
        background: var(--Main) !important;
        color: #fff
    }
    .chosen-container.chosen-with-drop .chosen-drop {
        margin: 0 12px;
        border: 1px solid var(--Input);
        width: -webkit-fill-available;
    }
    .chosen-container .chosen-results li {
        padding: 10px 15px;
    }
</style>