<?php
/** @var $this yii\web\View */

\Mitisk\Yii2Admin\assets\TrumbowygAsset::register($this);
?>
<div class="tf-section-2 mb-30 db-center">
    <?= yii\helpers\Html::csrfMetaTags() ?>
    <div class="wg-box db-notes">
        <div class="db-box-head">
            <div>
                <h5>Заметки</h5>
                <div class="db-box-head__hint">Видны только вам, сохраняются сами</div>
            </div>
            <div class="db-notes__state">
                <span class="db-notes__status" id="note-status" aria-live="polite"></span>
                <button type="button" id="btn-save" class="db-action" hidden>Сохранить</button>
            </div>
        </div>
        <textarea id="text-meta"></textarea>
    </div>

    <?= \Mitisk\Yii2Admin\widgets\IndexCenterComponentWidget::widget() ?>
</div>
