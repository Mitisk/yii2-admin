<?php
/** @var $this \yii\web\View */
?>
<div class="wg-chart-default dashboard-top-widget-item" data-name="IndexClearCacheWidget">

    <div class="dropdown default">
        <button class="btn btn-secondary dropdown-toggle" data-bs-offset="0,-16" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="icon-more"><i class="icon-more-horizontal"></i></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="">
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
            <div class="image">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="52" viewBox="0 0 48 52" fill="none">
                    <path opacity="0.08" d="M19.1086 2.12943C22.2027 0.343099 26.0146 0.343099 29.1086 2.12943L42.4913 9.85592C45.5853 11.6423 47.4913 14.9435 47.4913 18.5162V33.9692C47.4913 37.5418 45.5853 40.8431 42.4913 42.6294L29.1086 50.3559C26.0146 52.1423 22.2027 52.1423 19.1086 50.3559L5.72596 42.6294C2.63194 40.8431 0.725956 37.5418 0.725956 33.9692V18.5162C0.725956 14.9435 2.63195 11.6423 5.72596 9.85592L19.1086 2.12943Z" fill="url(#paint0_linear_cache)"/>
                    <defs>
                        <linearGradient id="paint0_linear_cache" x1="-43.532" y1="-34.3465" x2="37.6769" y2="43.9447" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#FF9292"/>
                            <stop offset="1" stop-color="#FC2323"/>
                        </linearGradient>
                    </defs>
                </svg>
                <i class="icon-trash"></i>
            </div>
            <div>
                <a class="tf-button style-1" href="<?= \yii\helpers\Url::to(['/admin/default/clear-cache']) ?>" id="btn-clear-cache">
                    Очистить кэш сайта
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$url = \yii\helpers\Url::to(['/admin/default/clear-cache']);
$js = <<<JS
$('#btn-clear-cache').on('click', function(e) {
    e.preventDefault();
    let btn = $(this);
    if(btn.hasClass('loading')) return;
    
    btn.addClass('loading');
    $.ajax({
        url: '$url',
        type: 'POST',
        success: function(res) {
            btn.removeClass('loading');
            if(res.success) {
                alert('Кэш успешно очищен');
            } else {
                alert('Ошибка при очистке кэша');
            }
        },
        error: function() {
            btn.removeClass('loading');
            alert('Ошибка запроса');
        }
    });
});
JS;
$this->registerJs($js);
?>
