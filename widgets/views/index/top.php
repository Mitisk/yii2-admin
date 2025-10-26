<?php
/** @var $this \yii\web\View */
/** @var $availableWidgets array */
?>

<div class="tf-section-4 mb-30 dashboard-top-widget-section">

    <?php
    if ($availableWidgets) {
        foreach ($availableWidgets as $widget) {
            if (class_exists($widget)) {
                echo $widget::widget();
            }
        }
    }
    ?>

    <div class="wg-chart-default wg-chart-add dashboard-add-top-widget-section">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap14">
                <div class="image">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="52" viewBox="0 0 48 52" fill="none">
                        <path opacity="0.08" d="M19.1086 2.12943C22.2027 0.343099 26.0146 0.343099 29.1086 2.12943L42.4913 9.85592C45.5853 11.6423 47.4913 14.9435 47.4913 18.5162V33.9692C47.4913 37.5418 45.5853 40.8431 42.4913 42.6294L29.1086 50.3559C26.0146 52.1423 22.2027 52.1423 19.1086 50.3559L5.72596 42.6294C2.63194 40.8431 0.725956 37.5418 0.725956 33.9692V18.5162C0.725956 14.9435 2.63195 11.6423 5.72596 9.85592L19.1086 2.12943Z" fill="url(#paint0_linear_53_110)"/>
                        <defs>
                            <linearGradient id="paint0_linear_53_110" x1="-43.532" y1="-34.3465" x2="37.6769" y2="43.9447" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#92BCFF"/>
                                <stop offset="1" stop-color="#2377FC"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <i class="icon-plus"></i>
                </div>
                <div>
                    <div class="body-text mb-2">Добавить виджет</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addWidgetModal" tabindex="-1" aria-labelledby="addWidgetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWidgetModalLabel">Добавить виджет</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addWidgetForm">
                    <div class="block-warning w-full mt-3 mb-6 d-none" id="widgetError"></div>
                    <div class="block-available w-full mt-3 mb-6 d-none" id="widgetSuccess"></div>
                    <div class="mb-3">
                        <label for="widgetClass" class="body-title mb-10">Класс виджета</label>
                        <input type="text" class="form-control" id="widgetClass" name="class"
                               placeholder="Например: \Mitisk\Yii2Admin\widgets\IndexUserWidget" required>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="tf-button style-2 tf-info" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="tf-button w208" id="addWidgetBtn">Добавить</button>
            </div>
        </div>
    </div>
</div>