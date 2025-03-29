<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/* @var $dataProvider \yii\data\ActiveDataProvider */

$this->title = $model->getComponentName();
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wg-box">
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

        <?php if($model->canCreate()): ?>
            <?= Html::a("<i class=\"icon-plus\"></i> Добавить", ['create', 'page-alias' => $model->component->alias], ['class' => 'tf-button style-1 w208']) ?>
        <?php endif; ?>
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
