<?php
/** @var $this \yii\web\View */
?>
<div class="wg-chart-default dashboard-top-widget-item db-card" data-name="IndexClearCacheWidget">
    <div class="dropdown default">
        <button class="btn btn-secondary dropdown-toggle" data-bs-offset="0,-16" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Действия с виджетом">
            <span class="icon-more"><i class="icon-more-horizontal"></i></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dashboard-top-widget-item-move" href="javascript:void(0);">Переместить</a></li>
            <li><a class="dashboard-top-widget-item-hide" href="javascript:void(0);">Скрыть</a></li>
        </ul>
    </div>
    <div class="db-stat">
        <span class="db-stat__icon db-stat__icon--orange"><i class="icon-refresh-ccw"></i></span>
        <span class="db-stat__body">
            <span class="db-stat__title">Кэш сайта</span>
            <span class="db-stat__note db-stat__note--lead">Очистите, если правки не видны на сайте</span>
            <?php // Обработчик — в assets/src/admin/dashboard.js ?>
            <a class="db-action" href="<?= \yii\helpers\Url::to(['/admin/default/clear-cache']) ?>" id="btn-clear-cache" role="button">Очистить кэш</a>
        </span>
    </div>
</div>
