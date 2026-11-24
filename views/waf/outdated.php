<?php
/**
 * Пакет WAF установлен, но его версия не умеет управляться из админки.
 *
 * @var yii\web\View $this
 */
?>
<div class="wg-box">
    <div class="block-warning type-main w-full">
        <i class="icon-alert-octagon"></i>
        <div class="body-title-2">
            В проекте стоит старая версия пакета mitisk/yii2-lightweight-waf. Управление из админки появилось
            в версии 2.1 — обновите пакет: <code>composer update mitisk/yii2-lightweight-waf</code>.
            До обновления WAF работает по настройкам из <code>web/index.php</code>, как раньше.
        </div>
    </div>
</div>
