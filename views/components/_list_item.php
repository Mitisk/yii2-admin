<?php
/**
 * Grid column row partial for the component update form.
 *
 * @category View
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 *
 * @var \yii\web\View                          $this
 * @var \Mitisk\Yii2Admin\models\AdminModel    $model
 * @var string                                 $column
 * @var array                                  $columnData
 * @var string                                 $name
 * @var array                                  $requiredColumns
 *
 * @php 8.0
 */

use yii\helpers\ArrayHelper;
use yii\helpers\Html;

$inputBase = Html::getInputName($model, 'list');
$isActions = ($column === 'admin_actions');
$isOn      = (bool)ArrayHelper::getValue($model->list, $column . '.on');
$switchId  = 'gc-' . $column;
$itemClass = 'grid-column-item list-draggable'
    . ($isActions ? ' grid-column-actions' : '');
?>
<div class="<?php echo $itemClass ?>">
    <div style="display:flex;align-items:center;flex:1;min-width:0;">

        <?php /* drag-area required by arrangeable({dragSelector:'.drag-area'}) */ ?>
        <div class="drag-area" style="padding-right:12px;cursor:grab;">
            <i class="fas fa-grip-vertical" style="color:#cbd5e1;font-size: 18px;"></i>
        </div>

        <?php if ($isActions) : ?>
            <span class="body-title"
                  style="font-size:14px;font-weight:600;color:#3b82f6;">
                <i class="fas fa-bolt me-1"></i>
                <?php echo Html::encode($name) ?>
            </span>
            <div class="grid-actions-config">
                <?php
                $viewVal   = ArrayHelper::getValue(
                    $columnData, 'data.view',   '0'
                );
                $updateVal = ArrayHelper::getValue(
                    $columnData, 'data.update', '0'
                );
                $deleteVal = ArrayHelper::getValue(
                    $columnData, 'data.delete', '0'
                );
                ?>
                <?php /* label must follow checkbox directly for CSS + selector */ ?>
                <input class="action-btn-check" type="checkbox"
                       id="gc-act-view"
                       <?php echo $viewVal   ? 'checked' : '' ?>>
                <label class="action-btn-label view" for="gc-act-view">
                    <i class="fas fa-eye me-1"></i> View
                </label>

                <input class="action-btn-check" type="checkbox"
                       id="gc-act-update"
                       <?php echo $updateVal ? 'checked' : '' ?>>
                <label class="action-btn-label update" for="gc-act-update">
                    <i class="fas fa-pen me-1"></i> Update
                </label>

                <input class="action-btn-check" type="checkbox"
                       id="gc-act-delete"
                       <?php echo $deleteVal ? 'checked' : '' ?>>
                <label class="action-btn-label delete" for="gc-act-delete">
                    <i class="fas fa-trash me-1"></i> Delete
                </label>

                <?php /* hidden inputs placed after labels (CSS + selector) */ ?>
                <?php echo Html::hiddenInput(
                    $inputBase . '[admin_actions][data][view]',
                    $viewVal,
                    ['id' => 'gc-act-view-h']
                ) ?>
                <?php echo Html::hiddenInput(
                    $inputBase . '[admin_actions][data][update]',
                    $updateVal,
                    ['id' => 'gc-act-update-h']
                ) ?>
                <?php echo Html::hiddenInput(
                    $inputBase . '[admin_actions][data][delete]',
                    $deleteVal,
                    ['id' => 'gc-act-delete-h']
                ) ?>
            </div>

        <?php else : ?>
            <div>
                <span class="body-title"
                      style="font-size:14px;font-weight:500;">
                    <?php echo Html::encode($name) ?>
                    <?php if (in_array($column, $requiredColumns, true)) : ?>
                        <span class="tf-color-1">*</span>
                    <?php endif; ?>
                </span>
                <div style="font-size:12px;color:#94a3b8;">
                    <?php echo Html::encode($column) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php /* total-checkbox is the admin theme's native toggle style */ ?>
    <div style="display:flex;align-items:center;gap:8px;margin-left:12px;">
        <input class="total-checkbox"
               id="<?php echo $switchId ?>"
               name="<?php echo $inputBase ?>[<?php echo $column ?>][on]"
               type="checkbox"
               value="1"
               <?php echo $isOn ? 'checked' : '' ?>>
        <?php echo Html::hiddenInput(
            $inputBase . '[' . $column . '][ordering]', '1'
        ) ?>
    </div>
</div>
<?php if ($isActions) : ?>
    <?php
    $this->registerJs(
        "
        ['view','update','delete'].forEach(function(act) {
            var cb  = document.getElementById('gc-act-' + act);
            var hid = document.getElementById('gc-act-' + act + '-h');
            if (!cb || !hid) return;
            hid.value = cb.checked ? '1' : '0';
            cb.addEventListener('change', function() {
                hid.value = cb.checked ? '1' : '0';
            });
        });
        "
    );
    ?>
<?php endif; ?>
