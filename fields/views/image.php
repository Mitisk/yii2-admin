<?php
/** @var $field \Mitisk\Yii2Admin\fields\ImageField */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/** @var $fieldId string */

use yii\helpers\Html;
use yii\helpers\Json;
use Mitisk\Yii2Admin\fields\FieldsHelper;

\Mitisk\Yii2Admin\assets\FieldImageAsset::register($this);

// Текущее изображение (если есть)
$files = FieldsHelper::getFiles($model->getModel(), $field->name);
$currentUrl = $files ? ($files[0]->getUrl() ?: $files[0]->path) : null;

$inputName = Html::getInputName($model->getModel(), $field->name);

// Формат кадрирования: конкретный или свободный (тогда — пресеты в окне).
$fixedAspect = ($field->aspectRatio !== null && $field->aspectRatio !== '')
    ? (string)$field->aspectRatio
    : '';
$aspectJs = Json::encode($fixedAspect);
$freeCrop = $fixedAspect === '';
?>
    <div class="form-group image-field" data-field="<?= $fieldId ?>">
        <label class="body-title mb-10">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <div class="image-field-zone js-image-zone <?= $currentUrl ? 'has-image' : '' ?>">
            <button type="button" class="image-field-card js-image-choose" tabindex="-1">
                <img class="js-image-thumb" src="<?= Html::encode((string)$currentUrl) ?>" alt=""
                     <?= $currentUrl ? '' : 'style="display:none;"' ?>>
                <span class="image-field-empty">
                    <i class="icon-upload-cloud"></i>
                    <span class="image-field-empty-title">Выбрать изображение</span>
                    <span class="image-field-empty-hint">JPG, PNG, GIF, WebP</span>
                </span>
                <span class="image-field-overlay">
                    <i class="icon-refresh-cw"></i> Заменить
                </span>
            </button>
        </div>

        <?= Html::fileInput($inputName, null, [
            'class' => 'js-image-input',
            'accept' => 'image/*',
            'id' => $fieldId,
            'style' => 'display:none;',
        ]); ?>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>

<!-- Модалка кадрирования -->
<div class="modal fade" id="<?= $fieldId ?>-crop-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="icon-crop"></i> Кадрирование</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($freeCrop) : ?>
                    <div class="image-crop-presets js-image-presets">
                        <span class="image-crop-presets-label">Формат:</span>
                        <button type="button" class="image-crop-preset is-active" data-ratio="">Свободно</button>
                        <button type="button" class="image-crop-preset" data-ratio="1">1:1</button>
                        <button type="button" class="image-crop-preset" data-ratio="4/3">4:3</button>
                        <button type="button" class="image-crop-preset" data-ratio="3/2">3:2</button>
                        <button type="button" class="image-crop-preset" data-ratio="16/9">16:9</button>
                        <button type="button" class="image-crop-preset" data-ratio="3/4">3:4</button>
                        <button type="button" class="image-crop-preset" data-ratio="9/16">9:16</button>
                    </div>
                <?php endif; ?>
                <div class="image-field-cropzone">
                    <img class="js-image-crop-src" src="" alt="">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="tf-button style-2 tf-info" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="tf-button style-1 js-image-apply"><i class="icon-check"></i> Применить</button>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
(function(){
    var wrap = document.querySelector('.image-field[data-field="{$fieldId}"]');
    if (!wrap || wrap.dataset.imgBound) { return; }
    wrap.dataset.imgBound = '1';

    var input   = wrap.querySelector('.js-image-input');
    var zone    = wrap.querySelector('.js-image-zone');
    var thumb   = wrap.querySelector('.js-image-thumb');
    var choose  = wrap.querySelector('.js-image-choose');
    var modalEl = document.getElementById('{$fieldId}-crop-modal');
    var cropImg = modalEl.querySelector('.js-image-crop-src');
    var applyBtn = modalEl.querySelector('.js-image-apply');
    var presets = modalEl.querySelector('.js-image-presets');
    var fixedAspect = {$aspectJs};

    var cropper = null;
    var objectUrl = null;
    var originalFile = null;
    var currentAspect = fixedAspect;
    var modal = new bootstrap.Modal(modalEl);

    // Безопасный разбор соотношения сторон ('16/9' | '1.5' | число) без eval
    function parseAspect(a) {
        if (a === null || a === undefined || a === '') { return NaN; }
        if (typeof a === 'number') { return a; }
        var s = String(a).trim();
        if (s.indexOf('/') !== -1) {
            var p = s.split('/');
            var n = parseFloat(p[0]), d = parseFloat(p[1]);
            return (d && !isNaN(n)) ? (n / d) : NaN;
        }
        var v = parseFloat(s);
        return isNaN(v) ? NaN : v;
    }

    choose.addEventListener('click', function(){ input.click(); });

    input.addEventListener('change', function(){
        var file = input.files && input.files[0];
        if (!file || file.type.indexOf('image/') !== 0) { return; }
        originalFile = file;
        if (objectUrl) { URL.revokeObjectURL(objectUrl); }
        objectUrl = URL.createObjectURL(file);
        cropImg.src = objectUrl;
        modal.show();
    });

    modalEl.addEventListener('shown.bs.modal', function(){
        if (cropper) { cropper.destroy(); }
        cropper = new Cropper(cropImg, {
            viewMode: 1,
            autoCropArea: 1,
            aspectRatio: parseAspect(currentAspect),
            background: false
        });
    });
    modalEl.addEventListener('hidden.bs.modal', function(){
        if (cropper) { cropper.destroy(); cropper = null; }
    });

    // Пресеты формата (только в свободном режиме)
    if (presets) {
        presets.addEventListener('click', function(e){
            var btn = e.target.closest('.image-crop-preset');
            if (!btn) { return; }
            currentAspect = btn.dataset.ratio || '';
            presets.querySelectorAll('.image-crop-preset').forEach(function(b){
                b.classList.toggle('is-active', b === btn);
            });
            if (cropper) { cropper.setAspectRatio(parseAspect(currentAspect)); }
        });
    }

    applyBtn.addEventListener('click', function(){
        if (!cropper || !originalFile) { return; }
        var canvas = cropper.getCroppedCanvas({ maxWidth: 4096, maxHeight: 4096 });
        canvas.toBlob(function(blob){
            if (!blob) { return; }
            var dt = new DataTransfer();
            dt.items.add(new File([blob], originalFile.name, { type: blob.type }));
            input.files = dt.files;

            var url = URL.createObjectURL(blob);
            thumb.src = url;
            thumb.style.display = '';
            zone.classList.add('has-image');

            modal.hide();
        }, originalFile.type);
    });
})();
JS
);

$this->registerCss(<<<CSS
.image-field-zone { max-width: 260px; }
.image-field-card {
    position: relative; display: block; width: 100%; padding: 0;
    border: 2px dashed #cbd5e1; border-radius: 14px; background: #f8fafc;
    cursor: pointer; overflow: hidden; aspect-ratio: 4 / 3;
    transition: border-color .15s ease, background .15s ease;
}
.image-field-card:hover { border-color: var(--Main, #2275fc); background: #f1f5f9; }
.image-field-zone.has-image .image-field-card {
    border-style: solid; border-color: #e6e8ec; background: #000;
}
.js-image-thumb { width: 100%; height: 100%; object-fit: cover; display: block; }
.image-field-empty {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 4px; color: #94a3b8;
}
.image-field-empty i { font-size: 34px; color: var(--Main, #2275fc); }
.image-field-empty-title { font-size: 14px; font-weight: 600; color: #475569; }
.image-field-empty-hint { font-size: 11px; }
.image-field-zone.has-image .image-field-empty { display: none; }
.image-field-overlay {
    position: absolute; inset: 0; display: none; align-items: center;
    justify-content: center; gap: 6px; color: #fff; font-size: 13px;
    font-weight: 600; background: rgba(15, 23, 42, .45);
}
.image-field-zone.has-image .image-field-card:hover .image-field-overlay { display: flex; }

.image-crop-presets {
    display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
    margin-bottom: 12px;
}
.image-crop-presets-label { font-size: 13px; color: #64748b; margin-right: 4px; }
.image-crop-preset {
    padding: 4px 12px; border: 1px solid #e6e8ec; border-radius: 8px;
    background: #fff; cursor: pointer; font-size: 13px; color: #475569;
}
.image-crop-preset:hover { border-color: var(--Main, #2275fc); }
.image-crop-preset.is-active {
    background: var(--Main, #2275fc); border-color: var(--Main, #2275fc); color: #fff;
}
.image-field-cropzone { max-height: 58vh; }
.image-field-cropzone img { display: block; max-width: 100%; }
CSS
);
?>
