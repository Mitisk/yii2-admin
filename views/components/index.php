<?php
/* @var $models \Mitisk\Yii2Admin\models\AdminModel[] */
/* @var $helper \Mitisk\Yii2Admin\components\ComponentHelper */
/* @var $this yii\web\View */

$this->title = 'Компоненты';
$this->params['breadcrumbs'][] = $this->title;
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        $(document).on('click', '.js-install-component', function(e) {
            e.preventDefault();

            var $installBtn = $(this);
            var alias = $installBtn.data('alias');

            // Прелоадер Bootstrap (зеленый spinner)
            var $loader = $('<div class="js-install-loader body-title" style="display:inline-block;">' +
                '<div class="spinner-border spinner-border-sm text-success" role="status" style="vertical-align:middle;">' +
                '<span class="visually-hidden">Загрузка...</span></div> <span style="vertical-align:middle;">Установка...</span>' +
                '</div>');

            // Скрываем кнопку и вставляем прелоадер
            $installBtn.hide().after($loader);

            $.ajax({
                url: '/admin/components/install/',
                type: 'POST',
                data: {alias: alias},
                complete: function() {
                    // Убираем прелоадер после завершения запроса
                    $loader.remove();
                },
                success: function(response) {
                    // После успешной установки показываем кнопку "Удалить"
                    var $deleteBtn = $('.js-delete-component[data-alias="' + alias + '"]');
                    if ($deleteBtn.length === 0) {
                        $deleteBtn = $('<a href="#" class="js-delete-component" data-alias="'+alias+'"><i class="icon-trash-2"></i><span class="body-title">Удалить</span></a>');
                        $installBtn.after($deleteBtn);
                    } else {
                        $deleteBtn.show();
                    }
                },
                error: function(xhr) {
                    alert('Ошибка установки компонента');
                    // Показываем кнопку обратно в случае ошибки
                    $installBtn.show();
                }
            });
        });
        $(document).on('click', '.js-delete-component', function (e) {
            e.preventDefault();

            var $installBtn = $(this);
            var alias = $installBtn.data('alias');

            // Прелоадер Bootstrap (зеленый spinner)
            var $loader = $('<div class="js-install-loader body-title" style="display:inline-block;">' +
                '<div class="spinner-border spinner-border-sm text-success" role="status" style="vertical-align:middle;">' +
                '<span class="visually-hidden">Загрузка...</span></div> <span style="vertical-align:middle;">Удаление...</span>' +
                '</div>');

            // Скрываем кнопку и вставляем прелоадер
            $installBtn.hide().after($loader);

            $.ajax({
                url: '/admin/components/uninstall/',
                type: 'POST',
                data: {alias: alias},
                complete: function() {
                    // Убираем прелоадер после завершения запроса
                    $loader.remove();
                },
                success: function(response) {
                    // После успешной установки показываем кнопку "Удалить"
                    var $deleteBtn = $('.js-install-component[data-alias="' + alias + '"]');
                    if ($deleteBtn.length === 0) {
                        $deleteBtn = $('<a href="#" class="js-install-component" data-alias="'+alias+'"><span class="body-title">Установить</span><i class="icon-arrow-right"></i></a>');
                        $installBtn.after($deleteBtn);
                    } else {
                        $deleteBtn.show();
                    }
                },
                error: function(xhr) {
                    alert('Ошибка установки компонента');
                    // Показываем кнопку обратно в случае ошибки
                    $installBtn.show();
                }
            });
        });
    });
</script>
<?php if ($helper->isEnabled) : ?>
    <div class="tf-section-4 mb-30">

        <?php foreach ($helper->getAvailableComponents() as $component) : ?>

            <div class="wg-goal">
                <div class="image">
                    <img src="<?= $component['image'] ?>" alt="">
                </div>
                <div class="left">
                    <h5 class="mb-14"><?= $component['name'] ?></h5>

                    <div class="body-text mb-14"><?= $component['description'] ?></div>

                    <?php $check = Yii::$app->componentHelper->checkComponentVersion($component['alias'], $component['version']);?>

                    <?php if ($check === false) : ?>
                        <a href="#" class="js-install-component" data-alias="<?= $component['alias'] ?>"><span class="body-title">Обновить</span><i class="icon-arrow-right"></i></a>
                    <?php elseif ($check === true) : ?>
                        <a href="#" class="js-delete-component" data-alias="<?= $component['alias'] ?>"><i class="icon-trash-2"></i> <span class="body-title">Удалить</span></a>
                    <?php else: ?>
                        <?php if ($component['price']) : ?>
                            <a href="https://api.keypage.ru/mitisk/components/buy/?alias=<?= $component['alias'] ?>" target="_blank"><span class="body-title">Купить</span><i class="icon-arrow-right"></i></a>
                        <?php else: ?>
                            <a href="#" class="js-install-component" data-alias="<?= $component['alias'] ?>"><span class="body-title">Установить</span><i class="icon-arrow-right"></i></a>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
                <div class="right">
                    <?php if ($check === false) : ?>
                    <div class="block-not-available">v <?= $component['version'] ?></div>
                    <?php endif; ?>
                    <?php if ($check === true) : ?>
                    <div class="block-published">v <?= $component['version'] ?></div>
                    <?php endif; ?>
                    <?php if ($check === null) : ?>
                    <div class="block-pending">v <?= $component['version'] ?></div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endforeach; ?>

    </div>
<?php endif; ?>
<div class="wg-box">
    <div class="wg-table table-all-attribute">
        <ul class="table-title flex gap20 mb-14">
            <li>
                <div class="body-title">Название</div>
            </li>
            <li>
                <div class="body-title">Таблица</div>
            </li>
            <li>
                <div class="body-title"></div>
            </li>
        </ul>
        <ul class="flex flex-column">

            <?php foreach ($models as $model): ?>
                <li class="attribute-item flex items-center justify-between gap20">
                    <div class="name">
                        <a href="#" class="body-title-2"><?= $model->name ?></a>
                    </div>
                    <div class="body-text"><?= $model->table_name ?></div>
                    <div class="list-icon-function">
                        <a href="/admin/components/update?id=<?= $model->id ?>" class="item edit">
                            <i class="icon-edit-3"></i>
                        </a>
                        <a href="/admin/components/delete?id=<?= $model->id ?>" class="item trash">
                            <i class="icon-trash-2"></i>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>

        </ul>
    </div>
</div>