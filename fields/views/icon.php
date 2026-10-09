<?php
/** @var $field \Mitisk\Yii2Admin\fields\IconField */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/** @var $fieldId string */
/** @var $icons string[] Список css-классов иконок темы */

use yii\helpers\Html;
use yii\helpers\Json;

$current = (string)Html::getAttributeValue($model->getModel(), $field->name);
?>
    <div class="form-group icon-field" data-field="<?= $fieldId ?>">
        <label class="body-title mb-10" for="<?= $fieldId ?>">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <div class="icon-field-control">
            <button type="button" class="icon-field-trigger js-icon-open" tabindex="-1">
                <i class="js-icon-preview <?= Html::encode($current ?: 'icon-image') ?>"></i>
            </button>
            <?= Html::activeInput('text', $model->getModel(), $field->name, [
                'class' => 'js-icon-input',
                'placeholder' => $field->placeholder ?: 'icon-…',
                'id' => $fieldId,
                'required' => $field->required,
                'readonly' => $field->readonly,
                'autocomplete' => 'off',
            ]); ?>
        </div>

        <div class="icon-field-picker js-icon-picker" hidden>
            <input type="text" class="icon-field-search js-icon-search"
                   placeholder="Поиск иконки…" autocomplete="off">
            <div class="icon-field-grid js-icon-grid"></div>
        </div>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>

<?php
$iconsJs = Json::encode($icons);
$this->registerJs(<<<JS
(function(){
    var wrap = document.querySelector('.icon-field[data-field="{$fieldId}"]');
    if (!wrap || wrap.dataset.iconBound) { return; }
    wrap.dataset.iconBound = '1';

    var icons = {$iconsJs};
    var input   = wrap.querySelector('.js-icon-input');
    var preview = wrap.querySelector('.js-icon-preview');
    var trigger = wrap.querySelector('.js-icon-open');
    var picker  = wrap.querySelector('.js-icon-picker');
    var search  = wrap.querySelector('.js-icon-search');
    var grid    = wrap.querySelector('.js-icon-grid');

    function syncPreview() {
        preview.className = 'js-icon-preview ' + (input.value.trim() || 'icon-image');
    }

    function renderGrid(filter) {
        filter = (filter || '').toLowerCase();
        var html = '';
        for (var i = 0; i < icons.length; i++) {
            var cls = icons[i];
            if (filter && cls.indexOf(filter) === -1) { continue; }
            html += '<button type="button" class="icon-field-cell" data-icon="'
                + cls + '" title="' + cls + '"><i class="' + cls + '"></i></button>';
        }
        grid.innerHTML = html || '<div class="icon-field-empty">Ничего не найдено</div>';
    }

    function open() {
        renderGrid(search.value);
        picker.hidden = false;
        search.focus();
    }
    function close() { picker.hidden = true; }

    trigger.addEventListener('click', function(){
        picker.hidden ? open() : close();
    });
    input.addEventListener('input', syncPreview);
    search.addEventListener('input', function(){ renderGrid(search.value); });

    grid.addEventListener('click', function(e){
        var cell = e.target.closest('.icon-field-cell');
        if (!cell) { return; }
        input.value = cell.dataset.icon;
        syncPreview();
        close();
    });

    // Закрытие по клику вне
    document.addEventListener('click', function(e){
        if (!picker.hidden && !wrap.contains(e.target)) { close(); }
    });
})();
JS
);

$this->registerCss(<<<CSS
.icon-field-control { display: flex; align-items: center; gap: 8px; }
.icon-field-trigger {
    flex: 0 0 auto; width: 44px; height: 44px; border: 1px solid #e6e8ec;
    border-radius: 10px; background: #fff; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 20px; color: var(--Main, #2275fc);
}
.icon-field-control .js-icon-input { flex: 1 1 auto; }
.icon-field-picker {
    margin-top: 8px; padding: 10px; border: 1px solid #e6e8ec;
    border-radius: 12px; background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
}
.icon-field-search {
    width: 100%; padding: 8px 10px; margin-bottom: 8px;
    border: 1px solid #e6e8ec; border-radius: 8px;
}
.icon-field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(36px, 1fr));
    gap: 4px;
    max-height: 260px; overflow-y: auto;
}
.icon-field-cell {
    height: 36px; border: 1px solid transparent; border-radius: 8px;
    background: transparent; cursor: pointer; font-size: 16px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #475569;
}
.icon-field-cell:hover {
    border-color: var(--Main, #2275fc);
    background: rgba(34, 117, 252, .08);
    color: var(--Main, #2275fc);
}
.icon-field-empty { padding: 16px; color: #95989d; grid-column: 1 / -1; text-align: center; }
CSS
);
?>
