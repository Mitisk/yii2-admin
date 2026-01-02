<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $maps Mitisk\Yii2Admin\models\AdminUserMap[] */

$this->title = 'Редактирование компонента';

$this->params['breadcrumbs'][] = ['label' => 'Компоненты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<form class="wg-box mb-20">
    <table class="table list-admin-user-map table-borderless">
        <thead>
            <tr>
                <th>Название</th>
                <th>Модель формы</th>
                <th>View</th>
                <th style="width: 100px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($maps)): ?>
                <?php foreach ($maps as $map): ?>
                    <tr data-id="<?= $map->id ?>">
                        <td><?= Html::input('text', 'title', $map->title, ['class' => 'form-control']) ?></td>
                        <td><?= Html::input('text', 'form', $map->form, ['class' => 'form-control']) ?></td>
                        <td><?= Html::input('text', 'view', $map->view, ['class' => 'form-control']) ?></td>
                        <td>
                            <button type="button" class="tf-button style-1 w-full btn-delete-map">Удалить</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="new-row">
                <td><?= Html::input('text', 'title', '', ['class' => 'form-control input-title', 'placeholder' => 'Название']) ?></td>
                <td><?= Html::input('text', 'form', '', ['class' => 'form-control input-form', 'placeholder' => 'app\models\TestModel']) ?></td>
                <td><?= Html::input('text', 'view', '', ['class' => 'form-control input-view', 'placeholder' => '@app/views/test/index.php']) ?></td>
                <td>
                    <button type="button" class="tf-button w-full btn-add-map">Добавить</button>
                </td>
            </tr>
        </tbody>
    </table>
</form>

<?php
$addUrl = Url::to(['add-user-map']);
$deleteUrl = Url::to(['delete-user-map']);

$script = <<<JS
$('.btn-add-map').on('click', function() {
    var row = $(this).closest('tr');
    var title = row.find('.input-title').val();
    var form = row.find('.input-form').val();
    var view = row.find('.input-view').val();
    
    if(!title || !form || !view) {
        alert('Заполните все поля');
        return;
    }

    $.ajax({
        url: '$addUrl',
        type: 'POST',
        data: {
            title: title,
            form: form,
            view: view,
            _csrf: yii.getCsrfToken()
        },
        success: function(res) {
            if(res.success) {
                var newRow = '<tr data-id="' + res.id + '">' +
                    '<td><input type="text" class="form-control" name="title" value="' + title + '"></td>' +
                    '<td><input type="text" class="form-control" name="form" value="' + form + '"></td>' +
                    '<td><input type="text" class="form-control" name="view" value="' + view + '"></td>' +
                    '<td><button type="button" class="tf-button style-1 w-full btn-delete-map">Удалить</button></td>' +
                    '</tr>';
                
                row.before(newRow);
                row.find('input').val('');
            } else {
                alert('Ошибка при сохранении: ' + JSON.stringify(res.errors));
            }
        }
    });
});

$(document).on('click', '.btn-delete-map', function() {
    if(!confirm('Вы уверены?')) return;
    
    var row = $(this).closest('tr');
    var id = row.data('id');
    
    $.ajax({
        url: '$deleteUrl',
        type: 'GET',
        data: {id: id},
        success: function(res) {
            if(res.success) {
                row.remove();
            } else {
                alert('Ошибка при удалении');
            }
        }
    });
});
JS;
$this->registerJs($script);
?>
