<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\ContentBlockForm $form */
/** @var string $rowKey */
/** @var array<string, mixed> $row */

use yii\helpers\Html;

$fields = $form->itemFields;
$base = Html::getInputName($form, 'items') . '[' . Html::encode($rowKey) . ']';
$name = static fn(string $f): string => $base . '[' . $f . ']';
$val = static fn(string $f): string => is_scalar($row[$f] ?? null) ? (string)$row[$f] : '';
$imageId = is_numeric($row['image'] ?? null) ? (int)$row['image'] : null;
?>
<div class="cb-item">
    <span class="cb-item-handle" title="Перетащите, чтобы изменить порядок"><i class="icon-move"></i></span>
    <div class="cb-item-body">
        <?php if (in_array('title', $fields, true)): ?>
            <input type="text" name="<?= $name('title') ?>" value="<?= Html::encode($val('title')) ?>" placeholder="Заголовок">
        <?php endif; ?>
        <?php if (in_array('text', $fields, true)): ?>
            <textarea rows="3" name="<?= $name('text') ?>" placeholder="Текст"><?= Html::encode($val('text')) ?></textarea>
        <?php endif; ?>
        <?php if (in_array('url', $fields, true)): ?>
            <input type="text" name="<?= $name('url') ?>" value="<?= Html::encode($val('url')) ?>" placeholder="Ссылка: /page или https://…">
        <?php endif; ?>
        <?php if (in_array('image', $fields, true)): ?>
            <div class="cb-item-image">
                <?php if ($imageId !== null && ($url = Yii::$app->blocks->imageUrlById($imageId))): ?>
                    <?= Html::img($url, ['alt' => '']) ?>
                    <label class="flex items-center gap10"><input type="checkbox" name="<?= $name('imageRemove') ?>" value="1"> <span class="body-text">убрать</span></label>
                <?php endif; ?>
                <input type="hidden" name="<?= $name('image') ?>" value="<?= $imageId ?? '' ?>">
                <input type="file" class="cb-file" style="max-width:420px" accept="image/*" name="<?= $name('imageFile') ?>">
            </div>
        <?php endif; ?>
    </div>
    <button type="button" class="cb-item-remove tf-button style-2" title="Удалить пункт" style="width:auto;padding:0 14px"><i class="icon-trash-2"></i></button>
</div>
