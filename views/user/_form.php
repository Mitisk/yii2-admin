<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $availableRoles array */
/* @var $assignedRoles array */

\Mitisk\Yii2Admin\assets\UserFormAsset::register($this);
$this->registerCss("
.rbac-section {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 24px;
}
.rbac-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 15px;
}
.role-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 8px;
}
.role-item:last-child {
    margin-bottom: 0;
}
.role-name {
    font-weight: 500;
    color: #007bff;
}
.role-description {
    font-size: 13px;
    color: #6c757d;
    margin-left: 10px;
}
/* Восстановление нативного чекбокса и размеров только в RBAC-блоке */
.rbac-checkbox-list .form-check-input {
  appearance: auto !important;
  -webkit-appearance: auto !important;
  -moz-appearance: auto !important;

  position: static !important;
  display: inline-block !important;
  width: 15px !important;
  height: 15px !important;
  margin: 0;
  margin-right: .5rem !important;

  transform: none !important;
  scale: 1 !important;
  opacity: 1 !important;
  z-index: auto !important;
  pointer-events: auto !important;

  background: initial !important;          /* убрать кастомные bg-спрайты */
  background-image: none !important;
  border: 1px solid #adb5bd !important;    /* аккуратная рамка по умолчанию */
  border-radius: .25rem !important;

  /* Цвет галочки и акцента — избегайте белого ! */
  accent-color: #0d6efd; /* или ваш брендовый цвет */
}

/* Кликабельность по подписи */
.rbac-checkbox-list .form-check-label {
  pointer-events: auto !important;
  cursor: pointer !important;
  font-size: 12px;
}

/* На всякий случай снять блокировки кликов с контейнера */
.rbac-checkbox-list, .rbac-checkbox-list * {
  pointer-events: auto !important;
}
");

$selected = array_keys($assignedRoles);
?>
<?php $form = ActiveForm::begin([
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => ''],
        'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
    ],
    'options' => [
        'class' => 'form-add-new-user form-style-2',
        'enctype' => 'multipart/form-data'
    ]
]) ?>
    <div class="wg-box">
        <div class="left">
            <?php if (!$model->isNewRecord): ?>
                <fieldset class="title mb-24">
                    <div class="body-title mb-10">Аватар</div>
                    <input type="file" name="files" data-fileuploader-default="<?php echo $model->image; ?>" data-fileuploader-files='<?php echo isset($avatar) ? json_encode(array($avatar)) : '';?>'>
                </fieldset>
            <?php endif; ?>

            <!-- RBAC Роли и разрешения -->
            <div class="rbac-section">
                <div class="rbac-title">
                    <i class="icon-shield"></i> Роли и разрешения
                </div>

                <?php if (Yii::$app->user->can('manageUserRoles')): ?>
                    <fieldset class="roles">
                        <div class="body-title mb-10">Назначить роли</div>

                        <div class="rbac-checkbox-list">
                            <?php foreach ($availableRoles as $value => $label): ?>
                                <?php $id = 'role-' . Html::encode($value); ?>
                                <div class="form-check mb-2">
                                    <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="<?= $id ?>"
                                            name="user_roles[]"
                                            value="<?= Html::encode($value) ?>"
                                        <?= in_array($value, $selected, true) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="<?= $id ?>">
                                        <?= Html::encode($label) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="icon-info"></i>
                        У вас нет прав на управление ролями пользователей
                    </div>
                <?php endif; ?>

                <?php if (!empty($assignedRoles)): ?>
                    <fieldset class="current-roles mb-24">
                        <div class="body-title mb-10">Текущие роли</div>
                        <div class="roles-list">
                            <?php foreach ($assignedRoles as $roleName): ?>
                                <?php
                                $authManager = Yii::$app->authManager;
                                $role = $authManager->getRole($roleName);
                                ?>
                                <div class="role-item">
                                    <span class="role-name"><?= Html::encode($roleName) ?></span>
                                    <?php if ($role && $role->description): ?>
                                        <span class="role-description"><?= Html::encode($role->description) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>
            </div>

            <?php if ($model->created_at): ?>
                <div class="body-title">Дата регистрации</div>
                <div class="body-text"><?= Yii::$app->formatter->asDatetime($model->created_at, 'long') ?></div>
            <?php endif ?>
            <?php if ($model->updated_at): ?>
                <div class="body-title mt-2">Дата изменения</div>
                <div class="body-text"><?= Yii::$app->formatter->asDatetime($model->updated_at, 'long') ?></div>
            <?php endif ?>
            <?php if ($model->last_login_at): ?>
                <div class="body-title mt-2">Дата активности</div>
                <div class="body-text"><?= Yii::$app->formatter->asDatetime($model->last_login_at, 'long') ?></div>
            <?php endif ?>
        </div>

        <div class="right flex-grow">
            <fieldset class="name mb-24">
                <?= $form->field($model, 'status')->dropDownList([
                    \Mitisk\Yii2Admin\models\AdminUser::STATUS_BLOCKED => 'Заблокирован',
                    \Mitisk\Yii2Admin\models\AdminUser::STATUS_ACTIVE => 'Активен'
                ], ['class' => 'tom-select']) ?>
            </fieldset>

            <fieldset class="name mb-24">
                <?= $form->field($model, 'username')->textInput(['maxlength' => 255])->label('Логин <span class="tf-color-1">*</span>') ?>
            </fieldset>

            <fieldset class="name mb-24">
                <?= $form->field($model, 'name')->textInput(['maxlength' => 255]) ?>
            </fieldset>

            <fieldset class="email mb-24">
                <?= $form->field($model, 'email')->textInput(['maxlength' => 255])->label('Email <span class="tf-color-1">*</span>') ?>
            </fieldset>

            <?php if ($model->email && Yii::$app->componentHelper->hasComponent('mfa')) : ?>
                <?php
                $widgetClass = Yii::$app->componentHelper->getNamespace('mfa') . '\AdminUserView';
                if (class_exists($widgetClass)) {
                    echo $widgetClass::widget([
                        'model' => $model,
                        'form' => $form
                    ]);
                }
                ?>
            <?php else: ?>
                <fieldset class="password mb-24">
                    <?= $form->field($model, 'password', [
                        'template' => '
                        <div class="input-wrapper">
                        {label}
                            {input}
                            <span class="show-pass">
                                <i class="icon-eye view"></i>
                                <i class="icon-eye-off hide"></i>
                            </span>
                            <span class="generate-pass" title="Сгенерировать пароль">
                                <i class="icon-refresh-ccw"></i>
                            </span>
                        </div>
                        {error}'
                    ])->textInput([
                        'maxlength' => 255,
                        'class' => 'password-input',
                        'type' => 'password'
                    ]) ?>
                </fieldset>
            <?php endif; ?>
        </div>
    </div>

    <div class="bot">
        <button class="tf-button w180" type="submit">Сохранить</button>
    </div>
<?php ActiveForm::end() ?>

<?php
$this->registerJs("
$(document).ready(function() {
    // Обновление списка ролей при изменении
    $('#user-roles-select').on('change', function() {
        var selectedRoles = $(this).val() || [];
        console.log('Выбранные роли:', selectedRoles);
    });

    // Показать подсказку при наведении на роль
    $('.role-item').hover(
        function() {
            $(this).css('background-color', '#e3f2fd');
        },
        function() {
            $(this).css('background-color', 'white');
        }
    );
});
");
?>