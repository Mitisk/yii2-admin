<?php
/**
 * Grid column row partial — слот-колонка пользовательских ссылок.
 *
 * @category View
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 *
 * @var \yii\web\View                       $this
 * @var \Mitisk\Yii2Admin\models\AdminModel $model
 * @var string                              $column
 * @var array                               $columnData
 *
 * @php 8.1
 */

use yii\helpers\ArrayHelper;
use yii\helpers\Html;

$inputBase = Html::getInputName($model, 'list');
$isOn      = (bool)ArrayHelper::getValue($columnData, 'on', true);
$slotName  = (string)ArrayHelper::getValue($columnData, 'name', 'Ссылки');
$items     = (array)ArrayHelper::getValue($columnData, 'items', []);
$switchId  = 'gc-' . $column;
?>
<div class="grid-column-item list-draggable link-slot-row"
     data-link-slot="<?= Html::encode($column) ?>">
    <div style="display:flex;align-items:center;flex:1;min-width:0;gap:10px;">

        <div class="drag-area" style="padding-right:12px;cursor:grab;">
            <i class="fas fa-grip-vertical"
               style="color:#cbd5e1;font-size:18px;"></i>
        </div>

        <span class="body-title"
              style="font-size:13px;color:#3b82f6;white-space:nowrap;">
            <i class="fas fa-link me-1"></i>
        </span>

        <input type="text"
               class="link-slot-row__name"
               data-role="slot-name"
               value="<?= Html::encode($slotName) ?>"
               placeholder="Название колонки"
               style="flex:0 0 180px;padding:4px 8px;border:1px solid #e2e8f0;
                      border-radius:6px;font-size:13px;">

        <div class="link-slot-row__drop" data-role="slot-drop"
             style="flex:1;min-height:34px;border:1px dashed #cbd5e1;
                    border-radius:6px;padding:4px;display:flex;
                    flex-wrap:wrap;gap:4px;background:#fff;">
            <?php foreach ($items as $linkId) : ?>
                <span class="link-slot-chip" draggable="true"
                      data-link-id="<?= Html::encode($linkId) ?>">
                    <span class="link-slot-chip__preview"
                          data-role="chip-preview">…</span>
                    <span class="remove" data-role="chip-remove">×</span>
                </span>
            <?php endforeach; ?>
        </div>

        <button type="button"
                class="link-card__delete"
                data-role="slot-remove"
                title="Удалить слот">
            <i class="fas fa-times"></i>
        </button>

        <?php /* Hidden inputs — порядок внутри DOM = порядок в POST */ ?>
        <div class="link-slot-row__inputs" data-role="slot-inputs">
            <?= Html::hiddenInput(
                $inputBase . '[' . $column . '][type]',
                'links'
            ) ?>
            <?= Html::hiddenInput(
                $inputBase . '[' . $column . '][name]',
                $slotName,
                ['data-role' => 'hidden-name']
            ) ?>
            <?php if (empty($items)) : ?>
                <?= Html::hiddenInput(
                    $inputBase . '[' . $column . '][items][]',
                    ''
                ) ?>
            <?php else : ?>
                <?php foreach ($items as $linkId) : ?>
                    <?= Html::hiddenInput(
                        $inputBase . '[' . $column . '][items][]',
                        (string)$linkId
                    ) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:8px;margin-left:12px;">
        <input class="total-checkbox"
               id="<?= $switchId ?>"
               name="<?= $inputBase ?>[<?= $column ?>][on]"
               type="checkbox"
               value="1"
               <?= $isOn ? 'checked' : '' ?>>
    </div>
</div>
