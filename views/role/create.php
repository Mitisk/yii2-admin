<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $availablePermissions array */
/* @var $availableRoles array */
/* @var $models \Mitisk\Yii2Admin\models\AdminModel[] */

$this->title = 'Создание роли';
$this->params['breadcrumbs'][] = ['label' => 'Роли', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss("
.wg-table.table-inheritance-role .item {
    background: white !important;
}
.wg-table.table-create-role>* {
    min-width: unset !important;
}
");
?>
<?= Html::beginForm('', 'POST', ['class' => "wg-order-detail"]) ?>
    <div class="left flex-grow">
        <div class="wg-box mb-24">
            <div class="row">
                <div class="col-md-8">
                    <fieldset class="name">
                        <div class="body-title mb-10">Имя роли <span class="tf-color-1">*</span></div>
                        <input type="text" name="description" class="flex-grow" required placeholder="Например: Редактор контента">
                    </fieldset>

                </div>
                <div class="col-md-4">
                    <fieldset class="name">
                        <div class="body-title mb-10">Алиас <span class="tf-color-1">*</span></div>
                        <input type="text" name="name" class="flex-grow" required placeholder="Например: editor">
                        <!--div class="form-text">Используйте английские буквы без пробелов</div>-->
                    </fieldset>
                </div>
            </div>

            <?php if ($availableRoles) : ?>
                <div class="wg-table table-create-role table-inheritance-role">
                    <ul class="table-title flex gap20 mb-14">
                        <li>
                            <div class="body-title">Наследование ролей</div>
                        </li>
                    </ul>
                    <ul class="row">
                        <?php foreach ($availableRoles as $roleKey => $roleItem) : ?>
                        <li class="item gap20 wrap-checkbox col-md-6">
                            <fieldset class="flex items-center gap10">
                                <input class="checkbox-item" type="checkbox" name="parent_roles[]" value="<?= $roleKey ?>" id="<?= $roleKey ?>">
                                <label for="<?= $roleKey ?>"><div class="body-text flex gap10"><?= $roleItem ?> <span class="block-pending"><?= $roleKey ?></span></div></label>
                            </fieldset>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="wg-table table-create-role">

                <ul class="table-title flex gap20 mb-14">
                    <li>
                        <div class="body-title">Разрешения</div>
                    </li>
                </ul>
                <ul class="flex flex-column">
                    <li class="item gap20 wrap-checkbox">
                        <div class="body-text">Система</div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="superAdmin" id="superAdmin">
                            <label for="superAdmin"><div class="body-text">Супер администратор</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="accessAdmin" id="accessAdmin">
                            <label for="accessAdmin"><div class="body-text">Доступ в админ панель</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="viewReports" id="viewReports">
                            <label for="viewReports"><div class="body-text">Просмотр отчетов</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="manageRoles" id="manageRoles">
                            <label for="manageRoles"><div class="body-text">Управление ролями</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="manageUserRoles" id="manageUserRoles">
                            <label for="manageUserRoles"><div class="body-text">Управление ролями пользователей</div></label>
                        </div>
                    </li>
                    <li class="item gap20 wrap-checkbox">
                        <div class="body-text">Пользователи</div>
                        <div class="flex items-center gap10">
                            <input class="total-checkbox" type="checkbox" id="allUsers">
                            <label for="allUsers"><div class="body-text">Все</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="viewUsers" id="viewUsers">
                            <label for="viewUsers"><div class="body-text">Просмотр</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="createUsers" id="createUsers">
                            <label for="createUsers"><div class="body-text">Создание</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="updateUsers" id="updateUsers">
                            <label for="updateUsers"><div class="body-text">Редактирование</div></label>
                        </div>
                        <div class="flex items-center gap10">
                            <input class="checkbox-item" type="checkbox" name="permissions[]" value="deleteUsers" id="deleteUsers">
                            <label for="deleteUsers"><div class="body-text">Удаление</div></label>
                        </div>
                    </li>

                    <?php if ($models) : ?>
                        <?php foreach ($models as $model) : ?>
                            <li class="item gap20 wrap-checkbox">
                                <div class="body-text"><?= $model->name ?></div>
                                <div class="flex items-center gap10">
                                    <input class="total-checkbox" type="checkbox" id="<?= $model->model_class ?>">
                                    <label for="<?= $model->model_class ?>"><div class="body-text">Все</div></label>
                                </div>
                                <div class="flex items-center gap10">
                                    <input class="checkbox-item" type="checkbox" name="permissions[]" value="<?= $model->model_class ?>\view" id="<?= $model->model_class ?>\view">
                                    <label for="<?= $model->model_class ?>\view"><div class="body-text">Просмотр</div></label>
                                </div>
                                <div class="flex items-center gap10">

                                    <input class="checkbox-item" type="checkbox" name="permissions[]" value="<?= $model->model_class ?>\create" id="<?= $model->model_class ?>\create" <?= (!$model->can_create ? 'disabled' : '') ?>>
                                    <label for="<?= $model->model_class ?>\create"><div class="body-text">Создание</div></label>
                                </div>
                                <div class="flex items-center gap10">
                                    <input class="checkbox-item" type="checkbox" name="permissions[]" value="<?= $model->model_class ?>\update" id="<?= $model->model_class ?>\update">
                                    <label for="<?= $model->model_class ?>\update"><div class="body-text">Редактирование</div></label>
                                </div>
                                <div class="flex items-center gap10">
                                    <input class="checkbox-item" type="checkbox" name="permissions[]" value="<?= $model->model_class ?>\delete" id="<?= $model->model_class ?>\delete">
                                    <label for="<?= $model->model_class ?>\delete"><div class="body-text">Удаление</div></label>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="bot">
            <button class="tf-button w180" type="submit">Сохранить</button>
        </div>
    </div>
    <div class="right">
        <div class="wg-box mb-20 gap10">
            <div class="body-title">Рекомендации по созданию ролей:</div>
            <div class="body-text">
                <ul class="small">
                    <li><strong>Имя роли</strong> должно быть уникальным и описательным.</li>
                    <li><strong>Наследование</strong> позволяет создавать иерархию ролей.</li>
                    <li><strong>Разрешения</strong> определяют функционал, доступный роли. Избегайте избыточных разрешений.</li>
                </ul>
            </div>
        </div>
        <div class="wg-box mb-20 gap10">
            <div class="body-title">Примеры ролей:</div>
            <div class="body-text">
                <ul>
                    <li class="body-text flex gap10 mb-3"><span class="block-pending">editor</span> - Редактор контента</li>
                    <li class="body-text flex gap10 mb-3"><span class="block-pending">manager</span> - Менеджер</li>
                    <li class="body-text flex gap10 mb-3"><span class="block-pending">support</span> - Техподдержка</li>
                </ul>
            </div>
        </div>
    </div>
<?= Html::endForm(); ?>
