<?php
/** @var $this yii\web\View */
/** @var $userComponent string */
/** @var $userComponentModel \Mitisk\Yii2Admin\core\models\AdminModel|null */

?>
<?php if ($userComponent): ?>
    <div class="wg-box">
        <div class="flex items-center justify-between">
            <h5><?= $userComponentModel->getComponentName() ?></h5>
        </div>
        <?php
        // Пагинация/сортировка/фильтры грида обновляют только виджет,
        // не переписывая URL и историю главной страницы.
        \yii\widgets\Pjax::begin(
            [
                'id' => 'dashboard-component-pjax',
                'timeout' => 8000,
                'enablePushState' => false,
            ]
        );
        ?>
        <?= $userComponent ?>
        <?php \yii\widgets\Pjax::end(); ?>
    </div>
<?php else: ?>
    <button type="button" class="db-empty dashboard-add-center-widget-section">
        <span class="db-add__icon" aria-hidden="true"><i class="icon-plus"></i></span>
        <span class="db-empty__title">Вывести раздел на главную</span>
        <span class="db-empty__text">Список записей любого раздела админки — например, новые заявки — будет здесь под рукой.</span>
    </button>
<?php endif; ?>