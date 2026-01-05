<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $availableRoles array */
/* @var $assignedRoles array */
/* @var $maps \Mitisk\Yii2Admin\models\AdminUserMap[] */
/* @var $extraModels array */

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

<?php if (isset($maps) && !empty($maps)): ?>
    <div class="widget-tabs">
        <ul class="widget-menu-tab style-1">
            <li class="item-title active">
                <span class="inner"><span class="h6">Основное</span></span>
            </li>
            <?php foreach ($maps as $map): ?>
                <li class="item-title">
                    <span class="inner"><span class="h6"><?= Html::encode($map->title) ?></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

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
    <?php ob_start(); ?>
    <div class="wg-box widget-content-inner active">
        <div class="left">
            <fieldset class="title mb-24">
                <div class="body-title mb-10">Аватар</div>
                <input type="file" name="files" data-fileuploader-default="<?php echo $model->getAvatar(); ?>" data-fileuploader-files='<?php echo isset($avatar) ? json_encode(array($avatar)) : '';?>'>
            </fieldset>

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
                <div class="body-text"><?= Yii::$app->formatter->asDatetime($model->created_at, 'd MMMM y HH:mm:ss') ?></div>
            <?php endif ?>
            <?php if ($model->updated_at): ?>
                <div class="body-title mt-2">Дата изменения</div>
                <div class="body-text"><?= Yii::$app->formatter->asDatetime($model->updated_at, 'd MMMM y HH:mm:ss') ?></div>
            <?php endif ?>
            <?php if ($model->online_at): ?>
                <div class="body-title mt-2">Дата активности</div>
                <div class="body-text"><?= Yii::$app->formatter->asDatetime($model->online_at, 'd MMMM y HH:mm:ss') ?></div>
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

            <fieldset class="name mb-24">
                <?= $form->field($model, 'auth_type')->dropDownList([
                    0 => 'Только пароль',
                    1 => 'Пароль и временный код',
                    2 => 'Только временный код'
                ]) ?>
            </fieldset>

            <fieldset class="name mb-24" id="field-mfa-secret">
                <?php
                if (!$model->mfa_secret) {
                    $model->mfa_secret = \Mitisk\Yii2Admin\components\MfaHelper::generateSecret();
                }
                $siteName = Yii::$app->settings->get('GENERAL', 'site_name', Yii::$app->request->getPathInfo());
                $otpUrl = \Mitisk\Yii2Admin\components\MfaHelper::getOtpAuthUrl($model->email ?? 'newUser', $siteName, $model->mfa_secret);
                $qrCodeUrl = 'https://quickchart.io/qr?text=' . urlencode($otpUrl) . '&size=100';
                echo Html::img($qrCodeUrl, ['id' => 'mfa-qr-code', 'data-secret' => $model->mfa_secret, 'data-issuer' => $siteName]);
                ?>
                <p class="right">Откройте на телефоне приложение для сохранения паролей, отсканируйте QR-код в открытом приложении.</p>
                <?= $form->field($model, 'mfa_secret')->hiddenInput()->label(false) ?>
            </fieldset>

            <fieldset class="password mb-24" id="field-password">
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
                    'id' => 'password-field',
                    'maxlength' => 255,
                    'class' => 'password-input',
                    'type' => 'password'
                ]) ?>
                <div style="margin-top: 10px;">
                <?php
                $tplId = Yii::$app->settings->get('Mitisk\Yii2Admin\models\AdminUser', 'mail_template_new_password');
                if ($tplId) {
                    echo $form->field($model, 'send_new_password')->checkbox();
                }
                ?>
                </div>
            </fieldset>

        </div>
    </div>
    <?php $mainContent = ob_get_clean(); ?>

    <?php if (isset($maps) && !empty($maps)): ?>

        <?= $mainContent ?>

        <?php foreach ($maps as $map): ?>
            <div class="widget-content-inner" style="display: none;">
                <?php
                if (isset($extraModels[$map->id])) {
                    echo $this->render($map->view, [
                        'form' => $form,
                        'model' => $extraModels[$map->id],
                    ]);
                }
                ?>
            </div>
        <?php endforeach; ?>

    <?php else: ?>

        <?= $mainContent ?>

    <?php endif; ?>

    <div class="bot">
        <button class="tf-button w180" type="submit">Сохранить</button>
    </div>
<?php ActiveForm::end() ?>

<?php
$this->registerJs("
$(document).ready(function() {
    // ТАБЫ
    $('.widget-menu-tab .item-title').on('click', function() {
        var index = $(this).index();
        $(this).addClass('active').siblings().removeClass('active');
        // Because content is now inside the form (detached from tabs container), we select by class within the form
        $('.form-add-new-user .widget-content-inner').removeClass('active').hide();
        $('.form-add-new-user .widget-content-inner').eq(index).addClass('active').show();
    });

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

    // Dynamic QR Code Update
    $('#" . Html::getInputId($model, 'email') . "').on('input change', function() {
        var email = $(this).val();
        var qrImg = $('#mfa-qr-code');
        var secret = qrImg.data('secret');
        var issuer = qrImg.data('issuer');

        if (email && secret && issuer) {
            var label = encodeURIComponent(issuer + ':' + email);
            var otpAuthUrl = 'otpauth://totp/' + label + '?secret=' + secret + '&issuer=' + encodeURIComponent(issuer);
            var qrUrl = 'https://quickchart.io/qr?text=' + encodeURIComponent(otpAuthUrl) + '&size=100';
            qrImg.attr('src', qrUrl);
        }
    });
});
function toggleFields() {
    var val = $('#" . \yii\helpers\Html::getInputId($model, 'auth_type') . "').val();

    if (val === '0') {
        // Только пароль
        $('#field-password').show();
        $('#field-mfa-secret').hide();
    } else if (val === '1') {
        // Пароль и временный код
        $('#field-password').show();
        $('#field-mfa-secret').show();
    } else if (val === '2') {
        // Только временный код
        $('#field-password').hide();
        $('#field-mfa-secret').show();
    }
}

// При загрузке страницы
toggleFields();

// При изменении выбора
$('#" . \yii\helpers\Html::getInputId($model, 'auth_type') . "').on('change', function() {
    toggleFields();
});
");
