<?php
/* @var $this yii\web\View */
/* @var $name string */
/* @var $description string */
/* @var $selected bool */
/* @var $disabled bool */
?>
<div class="flex items-center gap10">
    <input class="checkbox-item" type="checkbox" name="permissions[]" value="<?= $name ?>" id="<?= $name ?>"
        <?= $selected ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
    <label for="<?= $name ?>"><div class="body-text"><?= $description ?></div></label>
</div>
