<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/* @var $dataProvider \yii\data\ActiveDataProvider */

$this->title = $model->getComponentName();
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wg-box" data-model-class="<?= htmlspecialchars($model->getModelName()) ?>">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
            <form class="form-search">
                <fieldset class="name">
                    <input type="text" placeholder="Поиск..." class="" name="<?= $model->getModel()->formName() ?>[search]" tabindex="2" aria-required="true"
                           value="<?= \yii\helpers\ArrayHelper::getValue(Yii::$app->request->get(), $model->getModel()->formName() . '.search') ?>">
                </fieldset>
                <div class="button-submit">
                    <button class="" type="submit"><i class="icon-search"></i></button>
                </div>
            </form>
        </div>

        <?php if ($model->canCreate()) : ?>
            <?= Html::a("<i class=\"icon-plus\"></i> Добавить", ['create', 'page-alias' => $model->component->alias], ['class' => 'tf-button style-1 w208']) ?>
        <?php endif; ?>

        <?php if ($model->hasSettings()) : ?>
            <?= Html::a("<i class=\"icon-settings\"></i>", ['/admin/settings/', 'modelName' => $model->getModelName()], ['class' => 'tf-button']) ?>
        <?php endif; ?>

        <button type="button" class="tf-button style-2 tf-danger js-batch-delete-btn d-none" style="margin-left: 10px;">
            <i class="icon-trash-2"></i>
        </button>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $model->getModel(),
        'tableOptions' => ['class' => 'wg-table table-all-roles'],
        'rowOptions' => ['class' => "roles-item"],
        'contentOptions' => ['class' => "body-text"],
        'columns' => $model->getGridColumns(),
    ]) ?>
</div>

<div class="modal fade" id="batchDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Удалить выбранные элементы:</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- IDs will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="tf-button style-2 tf-info" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="tf-button style-2 tf-danger js-confirm-batch-delete"><i class="icon-trash-2"></i> Удалить</button>
            </div>
        </div>
    </div>
</div>
