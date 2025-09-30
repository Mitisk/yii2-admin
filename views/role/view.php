<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $role yii\rbac\Role */
/* @var $permissions array */
/* @var $children array */
/* @var $users array */

$this->title = 'Роль: ' . $role->name;
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
<div class="themesflat-container full">
    <div class="row">
        <div class="col-md-12 mb-20">

            <div class="wg-order-detail">
                <div class="left flex-grow">
                    <div class="wg-box mb-24">
                        <div class="wg-table table-create-role table-inheritance-role">
                            <ul class="table-title flex gap20 mb-14">
                                <li>
                                    <div class="body-title">Наследуемые роли</div>
                                </li>
                            </ul>
                            <?php
                            $childRoles = array_filter($children, function($child) {
                                return $child->type == 1; // Только роли
                            });
                            ?>
                            <?php if (empty($childRoles)): ?>
                                <div class="block-warning type-main w-full">
                                    <i class="fas fa-info-circle"></i>
                                    <div class="body-title-2">Данная роль не наследует другие роли.</div>
                                </div>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($childRoles as $childRole): ?>
                                        <li class="item gap20 wrap-checkbox" title="<?= Html::encode($childRole->description) ?>">
                                            <fieldset class="flex items-center gap10">
                                                <input class="checkbox-item" type="checkbox" checked disabled>
                                                <label><div class="body-text flex gap10"><?= Html::encode($childRole->description) ?> <span class="block-pending"><?= Html::encode($childRole->name) ?></span></div></label>
                                            </fieldset>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="block-pending mt-2">
                                    <i class="fas fa-info-circle"></i>
                                    <div class="body-text"><strong>Наследование:</strong> Эта роль автоматически получает все разрешения
                                        от указанных выше ролей.</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="wg-table table-all-attribute">
                            <ul class="table-title flex gap20 mb-14">
                                <li>
                                    <div class="body-title"> Разрешения роли</div>
                                </li>
                            </ul>
                            <?php if (empty($permissions)): ?>
                                <div class="block-warning type-main w-full">
                                    <i class="fas fa-info-circle"></i>
                                    <div class="body-title-2">У данной роли нет собственных разрешений.</div>
                                </div>
                            <?php else: ?>

                                <ul class="flex flex-column">
                                    <?php foreach ($permissions as $permission): ?>
                                    <li class="attribute-item flex items-center justify-between gap20">
                                        <div class="body-title-2">
                                            <?= Html::encode($permission->description ?: 'Без описания') ?>
                                        </div>
                                        <div class="body-text"><span class="block-pending"><?= Html::encode($permission->name) ?></span></div>
                                        <div class="list-icon-function"></div>
                                    </li>
                                    <?php endforeach; ?>

                                </ul>
                            <?php endif; ?>
                        </div>

                    </div>
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
                    </div>

                    <div class="wg-box mb-20 gap10">
                        <div class="body-title">Статистика</div>
                        <div class="body-text">
                            <ul>
                                <li class="body-text flex gap10 mb-3">Разрешений <span class="block-pending"><?= count($permissions) ?></span></li>
                                <li class="body-text flex gap10 mb-3">Наследуемых ролей <span class="block-pending"><?= count($childRoles) ?></span></li>
                                <li class="body-text flex gap10 mb-3">Пользователей <span class="block-pending"><?= ($users ? $users->count : 0) ?></span></li>
                            </ul>
                        </div>
                    </div>

                    <div class="wg-box gap10">
                        <div class="body-title">Действия с ролью</div>
                        <?= Html::a('Редактировать роль', ['update', 'name' => $role->name], [
                            'class' => 'tf-button style-1 w-full'
                        ]) ?>
                        <?= Html::a('Создать похожую роль', ['create'], [
                            'class' => 'tf-button style-1 w-full'
                        ]) ?>
                        <?php if (!in_array($role->name, ['admin', 'superAdminRole']) && empty($users)): ?>
                            <?= Html::a('Удалить роль', ['delete', 'name' => $role->name], [
                                'class' => 'tf-button style-2 tf-danger w-full',
                                'data-confirm' => 'Вы уверены, что хотите удалить роль "' . $role->name . '"?',
                                'data-method' => 'post',
                            ]) ?>
                        <?php else: ?>
                            <button class="tf-button style-2 tf-info w-full" disabled
                                    title="Роль нельзя удалить: она защищена или используется">
                                Нельзя удалить
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-md-12 mb-20">

            <div class="wg-box">
                <h5>Пользователи с ролью "<?= Html::encode($role->name) ?>"</h5>
                    <?php if (empty($users)): ?>
                        <div class="block-warning type-main w-full">
                            <i class="fas fa-info-circle"></i>
                            <div class="body-title-2">Данная роль пока не назначена ни одному пользователю.</div>
                        </div>
                    <?php else: ?>
                        <?= $this->render('@Mitisk/Yii2Admin/views/user/_list', [
                            'model' => new \Mitisk\Yii2Admin\models\AdminUser(),
                            'provider' => $users
                        ]) ?>
                    <?php endif; ?>
            </div>

        </div>
    </div>
</div>