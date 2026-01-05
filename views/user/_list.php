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
            'format' => 'raw', // важно для вывода HTML
            'value' => function ($model) {
                // онлайновость: online_at в пределах последних 5 минут
                $isOnline = $model->online_at && (time() - $model->online_at < 300);

                $avatar = \yii\helpers\Html::img($model->getAvatar(), ['alt' => '', 'class' => 'avatar-img']);
                $status = $isOnline
                    ? \yii\helpers\Html::tag('span', '', ['class' => 'status-circle status-online'])
                    : '';

                $image = \yii\helpers\Html::tag('div', $avatar . $status, ['class' => 'image avatar-wrap']);

                $nameHtml = ($model->name ? \yii\helpers\Html::encode($model->name) : \yii\helpers\Html::encode($model->username));
                $rolesHtml = \Mitisk\Yii2Admin\components\AdminDashboardHelper::getRolesById($model->id, false);

                $nameBlock = \yii\helpers\Html::tag('div',
                    $nameHtml . '<div class="text-tiny mt-3">' . $rolesHtml . '</div>',
                    ['class' => 'name']
                );

                $right = \yii\helpers\Html::tag('div', $nameBlock, ['class' => 'flex items-center justify-between gap20 flex-grow']);

                return '<div class="user-item gap14">' . $image . $right . '</div>';
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
            'template' => '{login-as} {update} {delete}',
            'buttons' => [
                'login-as' => function ($url, $model, $key) {
                    if (Yii::$app->user->id == $model->id || !Yii::$app->user->can('admin')) {
                        return '<div style="width: 20px"></div>';
                    }
                    if ($model->status != \Mitisk\Yii2Admin\models\AdminUser::STATUS_ACTIVE) {
                        return '<div title="Пользователь неактивен"><div class="item eye disabled"><i class="icon-log-in"></i></div></div>';
                    }
                    return Html::a(
                        '<div class="item eye"><i class="icon-log-in"></i></div>',
                        ['user/login-as', 'id' => $model->id],
                        [
                            'title' => 'Войти как этот пользователь',
                            'data-confirm' => 'Вы уверены, что хотите войти от имени этого пользователя?',

                        ]
                    );
                },
            ],
            'buttonOptions' => ['class' => '']
        ],
    ],
]);
