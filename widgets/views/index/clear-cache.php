<?php
/** @var $this \yii\web\View */
?>
<div class="wg-chart-default dashboard-top-widget-item" data-name="IndexClearCacheWidget">

    <div class="dropdown default">
        <button class="btn btn-secondary dropdown-toggle" data-bs-offset="0,-16" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="icon-more"><i class="icon-more-horizontal"></i></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dashboard-top-widget-item-move" href="javascript:void(0);">Переместить</a>
            </li>
            <li>
                <a class="dashboard-top-widget-item-hide" href="javascript:void(0);">Скрыть</a>
            </li>
        </ul>
    </div>

    <div class="flex items-center justify-between">
        <div class="flex items-center gap14">
            <div class="image db-icon db-icon--red">
                <i class="icon-trash"></i>
            </div>
            <div>
                <div class="body-text mb-2">Кэш сайта</div>
                <?php // Обработчик — в assets/src/admin/dashboard.js ?>
                <a class="db-action" href="<?= \yii\helpers\Url::to(['/admin/default/clear-cache']) ?>" id="btn-clear-cache" role="button">Очистить</a>
            </div>
        </div>
    </div>
</div>
