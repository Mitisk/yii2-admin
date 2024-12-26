<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;
?>
<div class="wg-box">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
            <form class="form-search">
                <fieldset class="name">
                    <input type="text" placeholder="Поиск..." class="" name="AuthItem[search]" tabindex="2" aria-required="true"
                           value="<?= \yii\helpers\ArrayHelper::getValue(Yii::$app->request->get(), 'AuthItem.search') ?>">
                </fieldset>
                <div class="button-submit">
                    <button class="" type="submit"><i class="icon-search"></i></button>
                </div>
            </form>
        </div>
        <?= Html::a("<i class=\"icon-plus\"></i> Добавить роль", ['create'], ['class' => 'tf-button style-1 w208']) ?>
    </div>
    <?= GridView::widget([
        'dataProvider' => $provider,
        'filterModel' => $model,
        'tableOptions' => ['class' => 'wg-table table-all-roles'],
        'rowOptions' => ['class' => "roles-item"],
        'contentOptions' => ['class' => "body-text"],
        'columns' => [
            [
                'header'=>'No',
                'class' => 'yii\grid\SerialColumn'
            ],
            'name',
            'description',
            'ruleName' => [
                'attribute' => 'ruleName',
                'filter' => [],
            ],
            [
                'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
                'buttonOptions' => ['class' => '']
            ],
        ],
    ]) ?>
</div>