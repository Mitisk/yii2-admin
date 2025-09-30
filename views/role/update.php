<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $role yii\rbac\Role */
/* @var $availablePermissions array */
/* @var $availableRoles array */
/* @var $currentPermissions array */
/* @var $currentParents array */
/* @var $isProtected bool */
/* @var $children array */
/* @var $usersCount int */

$this->title = 'Редактирование роли: ' . $role->name;
$this->params['breadcrumbs'][] = ['label' => 'Роли', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $role->name, 'url' => ['view', 'name' => $role->name]];
$this->params['breadcrumbs'][] = 'Редактирование';

$isAdmin = ($role->name == 'superAdminRole' || $role->name == 'admin');

$this->registerCss("
.wg-table.table-inheritance-role .item {
    background: white !important;
}
.wg-table.table-create-role>* {
    min-width: unset !important;
}
");
?>
<?php if ($isProtected): ?>
    <div class="protected-role mb-10">
        <div class="block-warning type-main w-full">
            <i class="fas fa-shield-alt fa-3x text-warning mr-4"></i>
            <div>
                <h4 class="mb-2">Защищенная роль</h4>
                <p class="mb-0">
                    Данная роль является системной и защищена от изменений.
                    Вы можете только просматривать её настройки.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= Html::beginForm('', 'POST', ['class' => "wg-order-detail"]) ?>
<div class="left flex-grow">
    <div class="wg-box mb-24">
        <div class="row">
            <div class="col-md-8">
                <fieldset class="name">
                    <div class="body-title mb-10">Имя роли</div>
                    <input type="text" name="description" class="form-control" value="<?= Html::encode($role->description) ?>" <?= $isProtected ? 'disabled' : '' ?>>
                </fieldset>
            </div>
            <div class="col-md-4">
                <fieldset class="name">
                    <div class="body-title mb-10">Алиас</div>
                    <input type="text" value="<?= Html::encode($role->name) ?>" class="form-control" disabled>
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
                                <input class="checkbox-item" type="checkbox" name="parent_roles[]" value="<?= $roleKey ?>"
                                       id="<?= $roleKey ?>" <?= in_array($roleKey, $currentParents) ? 'checked' : '' ?> <?= ($isProtected ? 'disabled' : '')?>>
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
                    <?php
                    $array = [
                        'superAdmin' => 'Супер администратор',
                        'accessAdmin' => 'Доступ в админ панель',
                        'viewReports' => 'Просмотр отчетов',
                        'manageRoles' => 'Управление ролями',
                        'manageUserRoles' => 'Управление ролями пользователей',
                    ];
                    foreach ($array as $key => $value) {
                        echo $this->render('_permission', [
                            'name' => $key,
                            'description' => $value,
                            'selected' => in_array($key, $currentPermissions),
                            'disabled' => $isProtected
                        ]);
                    } ?>
                </li>
                <li class="item gap20 wrap-checkbox">
                    <div class="body-text">Пользователи</div>
                    <div class="flex items-center gap10">
                        <?php if (!$isProtected) : ?>
                        <input class="total-checkbox" type="checkbox" id="allUsers">
                        <label for="allUsers"><div class="body-text">Все</div></label>
                        <?php endif; ?>
                    </div>
                    <?php
                    $array = [
                        'viewUsers' => 'Просмотр',
                        'createUsers' => 'Создание',
                        'updateUsers' => 'Редактирование',
                        'deleteUsers' => 'Удаление',
                    ];
                    foreach ($array as $key => $value) {
                        echo $this->render('_permission', [
                            'name' => $key,
                            'description' => $value,
                            'selected' => in_array($key, $currentPermissions),
                            'disabled' => $isProtected
                        ]);
                    } ?>
                </li>

                <?php if ($models) : ?>
                    <?php foreach ($models as $model) : ?>
                        <li class="item gap20 wrap-checkbox">
                            <div class="body-text"><?= $model->name ?></div>
                            <div class="flex items-center gap10">
                                <?php if (!$isProtected) : ?>
                                <input class="total-checkbox" type="checkbox" id="<?= $model->model_class ?>">
                                <label for="<?= $model->model_class ?>"><div class="body-text">Все</div></label>
                                <?php endif; ?>
                            </div>
                            <?php
                            $array = [
                                $model->model_class . '\view' => 'Просмотр',
                                $model->model_class . '\create' => 'Создание',
                                $model->model_class . '\update' => 'Редактирование',
                                $model->model_class . '\delete' => 'Удаление',
                            ];
                            foreach ($array as $key => $value) {
                                $cantCreate = ($key != $model->model_class . '\create' || ($key == $model->model_class . '\create' && $model->can_create));
                                echo $this->render('_permission', [
                                    'name' => $key,
                                    'description' => $value,
                                    'selected' => ((in_array($key, $currentPermissions) || $isAdmin) && $cantCreate),
                                    'disabled' => $isProtected
                                ]);
                            } ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <?php if (!$isProtected): ?>
        <div class="bot">
            <button class="tf-button w180" type="submit">Сохранить</button>
        </div>
    <?php endif; ?>
</div>
<div class="right">
    <div class="wg-box mb-20 gap10">
        <div class="body-title">Информация о роли</div>
        <div class="summary-item">
            <div class="body-text">Имя</div>
            <div class="body-title-2 tf-color-1"><?= Html::encode($role->name) ?></div>
        </div>
        <?php if ($role->description): ?>
            <div class="summary-item">
                <div class="body-text">Название</div>
                <div class="body-title-2"><?= Html::encode($role->description) ?></div>
            </div>
        <?php endif; ?>
        <div class="summary-item">
            <div class="body-text">Создана</div>
            <div class="body-title-2"><?= Yii::$app->formatter->asDatetime($role->createdAt, 'php:d.m.Y H:i:s') ?></div>
        </div>
        <?php if ($role->updatedAt != $role->createdAt): ?>
            <div class="summary-item">
                <div class="body-text">Обновлена</div>
                <div class="body-title-2"><?= Yii::$app->formatter->asDatetime($role->updatedAt, 'php:d.m.Y H:i:s') ?></div>
            </div>
        <?php endif; ?>
        <div class="summary-item">
            <div class="body-text">Тип</div>
            <div class="body-title-2">Роль</div>
        </div>
        <?php if ($isProtected): ?>
            <div class="block-warning w-full" style="min-width: 195px;">
                <i class="fas fa-shield-alt"></i>
                <div class="body-title-2">Системная роль</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="wg-box mb-20 gap10">
        <div class="body-title">Статистика</div>
        <div class="body-text">
            <?php
            $childRoles = array_filter($children, function($child) {
                return $child->type == 1; // Только роли
            });
            ?>
            <ul>
                <li class="body-text flex gap10 mb-3">Разрешений <span class="block-pending"><?= count($currentPermissions) ?></span></li>
                <li class="body-text flex gap10 mb-3">Наследуемых ролей <span class="block-pending"><?= count($childRoles) ?></span></li>
                <li class="body-text flex gap10 mb-3">Пользователей <span class="block-pending"><?= $usersCount ?></span></li>
            </ul>
        </div>
    </div>
</div>
<?= Html::endForm(); ?>
