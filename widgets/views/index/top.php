<?php
/** @var $this \yii\web\View */
/** @var $availableWidgets string[] */
/** @var $addable array<int, array{class: string, alias: string, title: string}> */
/** @var $canAddByClass bool */

use yii\helpers\Html;
?>

<div class="tf-section-4 mb-30 dashboard-top-widget-section db-top">

    <?php
    foreach ($availableWidgets as $widget) {
        if (class_exists($widget)) {
            echo $widget::widget();
        }
    }
    ?>

    <button type="button" class="db-add dashboard-add-top-widget-section" title="Добавить виджет на главную">
        <span class="db-add__icon" aria-hidden="true"><i class="icon-plus"></i></span>
        <span class="db-add__text">Добавить виджет</span>
    </button>
</div>

<div class="modal fade db-modal" id="addWidgetModal" tabindex="-1" aria-labelledby="addWidgetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWidgetModalLabel">Добавить виджет</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="db-modal__error" id="widgetError" hidden></div>

                <div class="db-pick" id="widgetPickList">
                    <?php foreach ($addable as $item): ?>
                        <div class="db-pick__item" data-widget-class="<?= Html::encode($item['class']) ?>" data-widget-alias="<?= Html::encode($item['alias']) ?>">
                            <div class="db-pick__body">
                                <div class="db-pick__title"><?= Html::encode($item['title']) ?></div>
                                <div class="db-pick__code"><?= Html::encode($item['alias']) ?></div>
                            </div>
                            <button type="button" class="tf-button style-1 db-pick__add">Добавить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="db-modal__empty" id="widgetPickEmpty"<?= $addable ? ' hidden' : '' ?>>Все доступные виджеты уже на главной. Скрытый виджет появится в этом списке — его можно будет вернуть.</p>

                <?php if ($canAddByClass): ?>
                    <details class="db-modal__dev">
                        <summary>Свой виджет по имени класса</summary>
                        <p class="db-modal__hint">Для разработчика: класс проекта — наследник <code>yii\base\Widget</code>. Чтобы виджет появился в списке у всех, зарегистрируйте его миграцией.</p>
                        <form id="addWidgetForm" class="db-modal__dev-form">
                            <input type="text" class="form-control" id="widgetClass" name="class" autocomplete="off"
                                   placeholder="app\widgets\admin\OrdersTodayWidget" aria-label="Класс виджета">
                            <button type="submit" class="tf-button" id="addWidgetBtn">Добавить</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
