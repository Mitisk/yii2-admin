<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $provider \yii\data\ActiveDataProvider */

echo GridView::widget([
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
            'controller' => '/admin/user',
            'template' => '{update} {delete}',
            'buttonOptions' => ['class' => '']
        ],
    ],
]);
