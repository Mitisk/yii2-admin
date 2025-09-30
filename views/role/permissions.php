<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ArrayDataProvider */

$this->title = 'Управление разрешениями';
$this->params['breadcrumbs'][] = ['label' => 'Роли', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerCss("
.wg-table.table-all-roles .roles-item>div:first-child, .wg-table.table-all-roles ul.table-title li:first-child {
    width: 300px !important;
}");
?>
<?php Pjax::begin(['id' => 'permissions-grid']); ?>
<div class="wg-box">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
            <?= Html::beginForm(['permissions'], 'get', ['data-pjax' => 1, 'class' => 'form-search']) ?>
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
    </div>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'wg-table table-all-roles'],
        'rowOptions' => ['class' => "roles-item"],
        'contentOptions' => ['class' => "body-text"],
        'columns' => [
            [
                'attribute' => 'name',
                'label' => 'Разрешение',
                'format' => 'raw',
                'value' => function ($model) {
                    $html = '<div class="permission-info">';
                    if ($model['description']) {
                        $html .= '<strong>' . Html::encode($model['description']) . '</strong>';
                    }
                    $html .= '<br><small class="block-pending mt-2">' . Html::encode($model['name']) . '</small>';
                    $html .= '</div>';

                    return $html;
                },
            ],
            [
                'label' => 'Используется в ролях',
                'format' => 'raw',
                'value' => function ($model) {
                    if (empty($model['roles'])) {
                        return '<span class="text-muted"><i>Не используется</i></span>';
                    }
                    $html = '<div class="permission-roles">';
                    foreach ($model['roles'] as $roleName) {
                        $html .= Html::a(
                            Html::encode($roleName),
                            ['view', 'name' => $roleName],
                            ['class' => 'block-pending mt-2', 'data-pjax' => 0]
                        );
                    }
                    $html .= '</div>';
                    return $html;
                },
            ],
            [
                'attribute' => 'rolesCount',
                'label' => 'Кол-во ролей',
                'contentOptions' => ['class' => 'text-center'],
                'format' => 'raw',
                'value' => function ($model) {
                    $count = (int)$model['rolesCount'];
                    $class = $count > 0 ? 'block-available' : 'block-not-available';

                    return '<span class="' . $class . '" title="Количество ролей"><i class="fa-solid fa-user-tag"></i> ' . $count . '</span>';
                },
            ],
            [
                'attribute' => 'createdAt',
                'label' => 'Создано',
                'format' => ['datetime', 'php:d.m.Y H:i:s'],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'header' => 'Действия',
                'template' => '{delete}',
                'urlCreator' => function ($action, $model, $key, $index) {
                    return ['delete-permission', 'name' => $model['name']];
                },
                'buttons' => [
                    'delete' => function ($url, $model, $key) {
                        // защищенные разрешения нельзя удалять
                        $protected = ['superAdmin', 'manageUserRoles', 'manageRoles'];
                        if (in_array($model['name'], $protected, true)) {
                            return '<span class="item disabled" title="Защищена от удаления" data-pjax="0">
                                        <i class="fa-light fa-shield-keyhole"></i>
                                    </span>';
                        }
                        // если разрешение используется хотя бы в одной роли — блокируем удаление
                        if ((int)$model['rolesCount'] > 0) {
                            return '<span class="item disabled" title="Используется в ролях" data-pjax="0">
                                        <i class="fa-light fa-shield-user"></i>
                                    </span>';
                        }
                        return Html::a('<i class="fas fa-trash-alt"></i>', $url, [
                            'title' => 'Удалить',
                            'class' => 'item trash',
                            'data-confirm' => 'Удалить разрешение \'' . Html::encode($model['name']) . '\'?',
                            'data-method' => 'post',
                            'data-pjax' => 0,
                        ]);
                    },
                ],
            ],
        ],
        'emptyText' => 'Разрешений пока нет',
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
            $.pjax.reload({container: '#permissions-grid'});
        });
    }
});
");
?>
