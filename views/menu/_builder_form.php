<?php
/* @var $this yii\web\View */
/* @var $permissions array Разрешения */
?>

<form id="frmEdit" class="wg-box">

    <fieldset>
        <label for="text" class="body-title mb-10">Название</label>
        <div class="input-group">
            <button type="button" id="myEditor_icon" class="btn btn-outline-secondary"></button>
            <input type="text" class="form-control item-menu" name="text" id="text" placeholder="Название">

        </div>
        <input type="hidden" name="icon" class="item-menu">
    </fieldset>

    <fieldset>
        <label for="href" class="body-title mb-10">URL</label>
        <input type="text" class="form-control item-menu" id="href" name="href" placeholder="URL">
    </fieldset>

    <fieldset>
        <label for="rule" class="body-title mb-10">Правило</label>
        <div class="select">
            <select name="rule" id="rule" class="item-menu">
                <?php foreach ($permissions as $value): ?>
                    <option value="<?= \yii\helpers\ArrayHelper::getValue($value, 'name') ?>">
                        <?= \yii\helpers\ArrayHelper::getValue($value, 'description') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </fieldset>

    <fieldset>
        <label for="target" class="body-title mb-10">Target</label>
        <div class="select">
            <select name="target" id="target" class="item-menu">
                <option value="_self">Self</option>
                <option value="_blank">Blank</option>
                <option value="_top">Top</option>
            </select>
        </div>
    </fieldset>

    <fieldset>
        <label for="title" class="body-title mb-10">Подсказка</label>
        <input type="text" name="title" class="form-control item-menu" id="title" placeholder="Подсказка">
    </fieldset>

    <div class="cols gap10">
        <button class="tf-button w-full" id="btnUpdate" disabled><i class="fas fa-sync-alt"></i> Обновить</button>
        <button class="tf-button style-1 w-full" id="btnAdd"><i class="fas fa-plus"></i> Добавить</button>
    </div>

</form>