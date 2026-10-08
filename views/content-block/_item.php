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
<div class="cb-item wg-box flex gap10 items-start" style="padding:12px">
    <span class="cb-item-handle" style="cursor:grab;padding-top:8px" title="Перетащите"><i class="icon-move"></i></span>
    <div class="flex-grow flex flex-column gap10">
        <?php if (in_array('title', $fields, true)): ?>
            <input type="text" class="form-control" name="<?= $name('title') ?>" value="<?= Html::encode($val('title')) ?>" placeholder="Заголовок">
        <?php endif; ?>
        <?php if (in_array('text', $fields, true)): ?>
            <textarea class="form-control" rows="2" name="<?= $name('text') ?>" placeholder="Текст"><?= Html::encode($val('text')) ?></textarea>
        <?php endif; ?>
        <?php if (in_array('url', $fields, true)): ?>
            <input type="text" class="form-control" name="<?= $name('url') ?>" value="<?= Html::encode($val('url')) ?>" placeholder="/page или https://…">
        <?php endif; ?>
        <?php if (in_array('image', $fields, true)): ?>
            <div class="flex gap10 items-center">
                <?php if ($imageId !== null && ($url = Yii::$app->blocks->imageUrlById($imageId))): ?>
                    <?= Html::img($url, ['style' => 'height:48px;border-radius:6px']) ?>
                    <label><input type="checkbox" name="<?= $name('imageRemove') ?>" value="1"> убрать</label>
                <?php endif; ?>
                <input type="hidden" name="<?= $name('image') ?>" value="<?= $imageId ?? '' ?>">
                <input type="file" accept="image/*" name="<?= $name('imageFile') ?>">
            </div>
        <?php endif; ?>
    </div>
    <button type="button" class="cb-item-remove tf-button style-2" title="Удалить пункт"><i class="icon-trash-2"></i></button>
</div>
