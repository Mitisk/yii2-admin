<?php
/** @var $this yii\web\View */

\Mitisk\Yii2Admin\assets\TrumbowygAsset::register($this);
?>
<div class="tf-section-2 mb-30">
    <?= yii\helpers\Html::csrfMetaTags() ?>
    <div class="wg-box">
        <div class="flex items-center justify-between">
            <h5>Заметки</h5>
        </div>
        <textarea id="text-meta"></textarea>
        <button id="btn-save" class="tf-button w-full style-2">Сохранить</button>
    </div>

    <?= \Mitisk\Yii2Admin\widgets\IndexCenterComponentWidget::widget() ?>
</div>