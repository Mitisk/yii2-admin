<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $provider \yii\data\ActiveDataProvider */

$this->params['breadcrumbs'][] = ['label' => 'Пользователи'];
$this->title = $this->params['pageHeaderText'] = 'Пользователи';
?>
<div class="wg-box">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
            <form class="form-search">
                <fieldset class="name">
                    <input type="text" placeholder="Поиск..." class="" name="<?= $model->formName() ?>[search]" tabindex="2" aria-required="true"
                           value="<?= \yii\helpers\ArrayHelper::getValue(Yii::$app->request->get(), $model->formName() . '.search') ?>">
                </fieldset>
                <div class="button-submit">
                    <button class="" type="submit"><i class="icon-search"></i></button>
                </div>
            </form>
        </div>
        <?= Html::a("<i class=\"icon-plus\"></i> Добавить пользователя", ['create'], ['class' => 'tf-button style-1']) ?>
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
            [
                'attribute' => 'name',
                'format' => 'raw',
                'value' => function ($model) {
                    $html = Html::tag('div', Html::img($model->image), ['class' => 'image']);
                    $html .= Html::tag('div',
                        Html::tag('div',
                            ($model->name ? Html::encode($model->name) : Html::encode($model->username)) . '<div class="text-tiny mt-3">' .
                            \Mitisk\Yii2Admin\components\AdminDashboardHelper::getRolesById($model->id, false) . '</div>',
                            ['class' => 'name']),
                        ['class' => 'flex items-center justify-between gap20 flex-grow']);

                    return '<div class="user-item gap14">' . $html . '</div>';
                },
            ],
            'email:email',
            [
                'attribute' => 'status',
                'filter' => [ // Фильтр для статуса
                    1 => 'Активен',
                    0 => 'Неактивен',
                ],
                'format' => 'raw',
                'value' => function ($model) {
                    return $model->status === 1 ? '<div class="block-available">Активен</div>' : '<div class="block-not-available">Неактивен</div>';
                },
            ],
            [
                'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
                'template' => '{update} {delete}',
                'buttonOptions' => ['class' => '']
            ],
        ],
    ]) ?>
</div>