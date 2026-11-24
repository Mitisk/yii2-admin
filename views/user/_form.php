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

            <?php
            // Секрет, который подключают сейчас: живёт в сессии до подтверждения кодом.
            // Действующий секрет пользователя в страницу не выводится.
            $mfaPendingSecret = \Mitisk\Yii2Admin\components\MfaSetup::secret($model->getMfaSetupId());
            $mfaIssuer = trim((string) Yii::$app->settings->get('GENERAL', 'site_name', '')) ?: (string) Yii::$app->request->hostName;
            $mfaConnected = $model->hasMfaApp();
            $authType = (int) $model->auth_type;
            $authMethods = [
                \Mitisk\Yii2Admin\models\LoginForm::PASSWORD => ['Пароль', 'Только пароль', null],
                \Mitisk\Yii2Admin\models\LoginForm::MFA_PASSWORD => ['Пароль и код', 'Пароль, затем код из приложения на телефоне', 'Надёжнее'],
                \Mitisk\Yii2Admin\models\LoginForm::MFA => ['Только код', 'Без пароля — по коду из приложения', null],
            ];
            ?>
            <div class="uf-auth" id="uf-auth">
                <div class="uf-auth__title">Способ входа</div>
                <p class="uf-auth__lead">Как пользователь подтверждает, что это он, когда входит в админку.</p>

                <div class="uf-methods" role="radiogroup" aria-label="Способ входа">
                    <?php foreach ($authMethods as $value => [$title, $text, $badge]): ?>
                        <label class="uf-method<?= $authType === $value ? ' is-active' : '' ?>">
                            <input type="radio" name="<?= Html::getInputName($model, 'auth_type') ?>" value="<?= $value ?>"<?= $authType === $value ? ' checked' : '' ?>>
                            <span class="uf-method__title">
                                <?= Html::encode($title) ?>
                                <?php if ($badge !== null): ?><span class="uf-method__badge"><?= Html::encode($badge) ?></span><?php endif; ?>
                            </span>
                            <span class="uf-method__text"><?= Html::encode($text) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= Html::error($model, 'auth_type', ['class' => 'uf-error']) ?>

                <div class="uf-mfa" id="uf-mfa"<?= $model->usesCode() ? '' : ' hidden' ?>>
                    <?php if ($mfaConnected): ?>
                        <div class="uf-mfa__status" id="uf-mfa-connected"<?= $model->mfa_reset ? ' hidden' : '' ?>>
                            <span class="uf-mfa__ok" aria-hidden="true"><i class="icon-check"></i></span>
                            <div class="uf-mfa__status-body">
                                <div class="uf-mfa__status-title">Приложение подключено</div>
                                <div class="uf-mfa__status-text">Коды для входа показывает приложение-аутентификатор на телефоне. Ключ подключения больше не показывается.</div>
                            </div>
                            <button type="button" class="uf-link" id="uf-mfa-reset-btn">Подключить заново</button>
                        </div>
                        <input type="hidden" name="<?= Html::getInputName($model, 'mfa_reset') ?>" id="uf-mfa-reset" value="<?= $model->mfa_reset ? '1' : '0' ?>">
                    <?php endif; ?>

                    <div id="uf-mfa-setup"
                         data-mfa-secret="<?= Html::encode($mfaPendingSecret) ?>"
                         data-mfa-issuer="<?= Html::encode($mfaIssuer) ?>"
                         data-mfa-account="<?= Html::encode((string) ($model->username ?: $model->email)) ?>"
                         <?= $mfaConnected && !$model->mfa_reset ? 'hidden' : '' ?>>
                        <div class="uf-mfa__setup">
                            <div class="uf-mfa__qr" id="uf-mfa-qr"></div>
                            <ol class="uf-steps">
                                <li>Откройте на телефоне <strong>приложение-аутентификатор</strong>: Яндекс Ключ, Google Authenticator, 1Password или другое.</li>
                                <li>
                                    Отсканируйте в нём QR-код. Если камера не помогает, введите ключ вручную:
                                    <div class="uf-key-row">
                                        <code class="uf-key" id="uf-mfa-key"><?= Html::encode(trim(chunk_split($mfaPendingSecret, 4, ' '))) ?></code>
                                        <button type="button" class="uf-link" id="uf-mfa-copy">Скопировать</button>
                                    </div>
                                </li>
                                <li>
                                    <label for="uf-mfa-code">Введите <strong>6 цифр</strong>, которые показало приложение</label>
                                    <input type="text" id="uf-mfa-code" name="<?= Html::getInputName($model, 'mfa_code') ?>"
                                           class="uf-code<?= $model->hasErrors('mfa_code') ? ' is-invalid' : '' ?>"
                                           inputmode="numeric" autocomplete="off" maxlength="6" placeholder="000000"
                                           aria-describedby="uf-mfa-code-error">
                                    <div class="uf-error" id="uf-mfa-code-error"><?= Html::encode($model->getFirstError('mfa_code')) ?></div>
                                </li>
                            </ol>
                        </div>
                        <?php if ($mfaConnected): ?>
                            <p class="uf-note"<?= $model->mfa_reset ? '' : ' hidden' ?>>
                                После сохранения прежнее приложение перестанет подходить для входа.
                                <button type="button" class="uf-link" id="uf-mfa-reset-cancel">Оставить прежнее</button>
                            </p>
                        <?php else: ?>
                            <p class="uf-note">Код подтверждает, что приложение настроено: без него вход по коду не включится и пользователь не останется без доступа.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <fieldset class="password" id="field-password"<?= $model->usesPassword() ? '' : ' hidden' ?>>
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
                        'type' => 'password',
                        'autocomplete' => 'new-password',
                    ])->label($model->hasNoPassword() ? 'Пароль <span class="tf-color-1">*</span>' : 'Новый пароль') ?>
                    <?php if (!$model->hasNoPassword()): ?>
                        <div class="uf-hint">Оставьте пустым, чтобы не менять пароль.</div>
                    <?php endif; ?>
                    <div style="margin-top: 10px;">
                    <?php
                    $tplId = Yii::$app->settings->get('Mitisk\Yii2Admin\models\AdminUser', 'mail_template_new_password');
                    if ($tplId) {
                        echo $form->field($model, 'send_new_password')->checkbox();
                    }
                    ?>
                    </div>
                </fieldset>

                <div class="uf-nopass" id="uf-nopass-note"<?= $model->usesPassword() ? ' hidden' : '' ?>>
                    Вход без пароля: при входе спрашивается только код из приложения.<?= $model->hasNoPassword() ? '' : ' Прежний пароль перестанет действовать.' ?>
                </div>
            </div>

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
