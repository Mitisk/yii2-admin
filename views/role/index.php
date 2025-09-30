<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ArrayDataProvider */

$this->title = 'Управление ролями';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss("
.wg-table.table-all-roles .roles-item>div:first-child, .wg-table.table-all-roles ul.table-title li:first-child {
    width: 300px !important;
}");
?>
<?php Pjax::begin(['id' => 'roles-grid']); ?>
    <div class="wg-box">
        <div class="flex items-center justify-between gap10 flex-wrap">
            <div class="wg-filter flex-grow">
                <?= Html::beginForm(['index'], 'get', ['data-pjax' => 1, 'class' => 'form-search']) ?>
                    <fieldset class="name">
                        <?= Html::textInput('search', Yii::$app->request->get('search'), [
                            'placeholder' => 'Поиск...',
                            'autocomplete' => 'off'
                        ]) ?>
                    </fieldset>
                    <div class="button-submit">
                        <button class="" type="submit"><i class="icon-search"></i></button>
                    </div>
                <?= Html::endForm() ?>
            </div>
                <?= Html::a('<i class="fa-duotone fa-solid fa-plus"></i> Создать роль', ['create'], [
                    'class' => 'tf-button style-2'
                ]) ?>
                <?= Html::a('Разрешения', ['permissions'], [
                    'class' => 'tf-button style-2'
                ]) ?>
        </div>


        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'wg-table table-all-roles'],
            'rowOptions' => ['class' => "roles-item"],
            'contentOptions' => ['class' => "body-text"],
            'columns' => [
                [
                    'attribute' => 'description',
                    'label' => 'Роль',
                    'format' => 'raw',
                    'value' => function ($model) {
                        $html = '<div class="role-name">';
                        if ($model['description']) {
                            $html .= '<strong>' . Html::encode($model['description']) . '</strong>';
                        }
                        $html .= '<br><small class="block-pending mt-2">' . Html::encode($model['name']) . '</small>';

                        $html .= '</div>';
                        return $html;
                    },
                ],
                [
                    'attribute' => 'createdAt',
                    'label' => 'Создана',
                    'format' => ['datetime', 'php:d.m.Y H:i:s'],
                ],
                [
                    'label' => 'Статистика',
                    'format' => 'raw',
                    'value' => function ($model) {
                        $html = '<div class="flex gap6">';
                        $html .= '<span class="block-published" title="Разрешения">';
                        $html .= '<i class="fas fa-key"></i> ' . $model['permissionsCount'];
                        $html .= '</span>';
                        $html .= '<span class="block-available" title="Пользователи">';
                        $html .= '<i class="fas fa-users"></i> ' . $model['usersCount'];
                        $html .= '</span>';
                        $html .= '</div>';
                        return $html;
                    },
                ],

                [
                    'class' => 'yii\grid\ActionColumn',
                    'header' => 'Действия',
                    'template' => '{view} {update} {delete}',
                    'urlCreator' => function ($action, $model, $key, $index) {
                        return [$action, 'name' => $model['name']];
                    },
                    'buttons' => [
                        'view' => function ($url, $model, $key) {
                            return Html::a('<i class="icon-eye"></i>', $url, [
                                'title' => 'Просмотр',
                                'class' => 'item eye',
                            ]);
                        },
                        'update' => function ($url, $model, $key) {
                            return Html::a('<i class="icon-edit-3"></i>', $url, [
                                'title' => 'Редактировать',
                                'class' => 'item edit',
                            ]);
                        },
                        'delete' => function ($url, $model, $key) {
                            // Защищенные роли нельзя удалять
                            if (in_array($model['name'], ['admin', 'superAdminRole'])) {
                                return '<span class="item disabled" title="Защищена от удаления">
                                        <i class="fa-light fa-shield-keyhole"></i>
                                    </span>';
                            }

                            if ($model['usersCount'] > 0) {
                                return '<span class="item disabled" title="Роль используется">
                                        <i class="fa-light fa-shield-user"></i>
                                    </span>';
                            }

                            return Html::a('<i class="icon-trash-2"></i>', $url, [
                                'title' => 'Удалить',
                                'class' => 'item trash',
                                'data-confirm' => 'Вы уверены, что хотите удалить роль "' . $model['name'] . '"?',
                                'data-method' => 'post',
                            ]);
                        },
                    ],
                ],
            ],

        ]); ?>


    </div>
<?php Pjax::end(); ?>

<?php
$this->registerJs("
$(document).on('click', '.btn-outline-danger[data-method=post]', function(e) {
    e.preventDefault();
    var link = $(this);
    var message = link.data('confirm');

    if (confirm(message)) {
        $.post(link.attr('href'), {}, function(data) {
            $.pjax.reload({container: '#roles-grid'});
        });
    }
});
");
?>