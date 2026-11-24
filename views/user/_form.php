<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;
use Mitisk\Yii2Admin\models\AdminUser;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $availableRoles array */
/* @var $assignedRoles array */
/* @var $maps \Mitisk\Yii2Admin\models\AdminUserMap[] */
/* @var $extraModels array */

\Mitisk\Yii2Admin\assets\UserFormAsset::register($this);

$assigned = array_keys($assignedRoles);
$canManageRoles = Yii::$app->user->can('manageUserRoles');
$isSelf = !$model->isNewRecord && (int) $model->id === (int) Yii::$app->user->id;
$isActive = (int) $model->status === AdminUser::STATUS_ACTIVE;
$displayName = trim((string) ($model->name ?: $model->username));
$authManager = Yii::$app->authManager;

// Роли, которые назначены, но текущему администратору недоступны для изменения
$lockedRoles = array_values(array_diff($assigned, array_keys($availableRoles)));

$formatter = Yii::$app->formatter;
$moments = array_filter([
    'Создан' => $model->created_at,
    'Изменён' => $model->updated_at,
    'Был в сети' => $model->online_at,
]);
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
        'class' => 'form-add-new-user form-style-2 uf-form',
        'enctype' => 'multipart/form-data'
    ]
]) ?>
    <?php ob_start(); ?>
    <div class="widget-content-inner active uf-layout">

        <aside class="uf-side">
            <div class="uf-card uf-profile">
                <div class="uf-profile__avatar">
                    <input type="file" name="files" data-fileuploader-default="<?php echo $model->getAvatar(); ?>" data-fileuploader-files='<?php echo isset($avatar) ? json_encode(array($avatar)) : '';?>'>
                </div>
                <div class="uf-profile__name" id="uf-profile-name" data-empty="Новый пользователь"><?= Html::encode($displayName !== '' ? $displayName : 'Новый пользователь') ?></div>
                <div class="uf-profile__login" id="uf-profile-login"<?= $model->username ? '' : ' hidden' ?>>@<span><?= Html::encode((string) $model->username) ?></span></div>
                <div class="uf-chip <?= $isActive ? 'uf-chip--ok' : 'uf-chip--off' ?>" id="uf-profile-status"><?= $isActive ? 'Активен' : 'Заблокирован' ?></div>

                <?php if ($moments): ?>
                    <dl class="uf-meta">
                        <?php foreach ($moments as $label => $time): ?>
                            <div class="uf-meta__row">
                                <dt><?= $label ?></dt>
                                <dd title="<?= Html::encode($formatter->asDatetime($time, 'd MMMM y, HH:mm')) ?>"><?= Html::encode($formatter->asRelativeTime($time)) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php else: ?>
                    <p class="uf-profile__hint">Аватар можно загрузить сейчас или позже — в карточке пользователя.</p>
                <?php endif; ?>
            </div>
        </aside>

        <div class="uf-main">

            <section class="uf-card">
                <div class="uf-card__title">Основное</div>
                <p class="uf-card__lead">Логин и email нужны для входа и писем, имя видно в админке.</p>

                <div class="uf-grid">
                    <fieldset class="name">
                        <?= $form->field($model, 'username')->textInput(['maxlength' => 255, 'autocomplete' => 'off'])->label('Логин <span class="tf-color-1">*</span>') ?>
                    </fieldset>

                    <fieldset class="name">
                        <?= $form->field($model, 'name')->textInput(['maxlength' => 255, 'placeholder' => 'Как показывать в админке']) ?>
                    </fieldset>

                    <fieldset class="email">
                        <?= $form->field($model, 'email')->textInput(['maxlength' => 255, 'type' => 'email', 'autocomplete' => 'off'])->label('Email <span class="tf-color-1">*</span>') ?>
                    </fieldset>

                    <div class="uf-status">
                        <div class="body-title mb-10">Статус</div>
                        <div class="uf-switch" role="radiogroup" aria-label="Статус">
                            <?php foreach ([AdminUser::STATUS_ACTIVE => 'Активен', AdminUser::STATUS_BLOCKED => 'Заблокирован'] as $value => $label): ?>
                                <label class="uf-switch__item<?= $value === AdminUser::STATUS_BLOCKED ? ' uf-switch__item--off' : '' ?>">
                                    <input type="radio" name="<?= Html::getInputName($model, 'status') ?>" value="<?= $value ?>"
                                        <?= ((int) $model->status === $value) ? 'checked' : '' ?><?= $isSelf ? ' disabled' : '' ?>>
                                    <span><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="uf-hint"><?= $isSelf
                            ? 'Свой аккаунт заблокировать нельзя.'
                            : 'Заблокированный пользователь не может войти в админку.' ?></div>
                        <?= Html::error($model, 'status', ['class' => 'uf-error']) ?>
                    </div>
                </div>
            </section>

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
            <section class="uf-card uf-auth" id="uf-auth">
                <div class="uf-card__title">Способ входа</div>
                <p class="uf-card__lead">Как пользователь подтверждает, что это он, когда входит в админку.</p>

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
            </section>

            <section class="uf-card">
                <div class="uf-card__title">Роли</div>
                <?php if ($canManageRoles): ?>
                    <p class="uf-card__lead">Роль определяет, какие разделы админки доступны. Можно отметить несколько.</p>
                    <div class="uf-roles">
                        <?php foreach ($availableRoles as $value => $label): ?>
                            <?php $checked = in_array($value, $assigned, true); ?>
                            <label class="uf-role<?= $checked ? ' is-active' : '' ?>">
                                <input type="checkbox" name="user_roles[]" value="<?= Html::encode($value) ?>"<?= $checked ? ' checked' : '' ?>>
                                <span class="uf-role__body">
                                    <span class="uf-role__title"><?= Html::encode($label) ?></span>
                                    <span class="uf-role__code"><?= Html::encode($value) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($lockedRoles): ?>
                        <div class="uf-roles-locked">
                            <span class="uf-hint">Назначены, но изменить их вы не можете:</span>
                            <?php foreach ($lockedRoles as $roleName): ?>
                                <?php $role = $authManager->getRole($roleName); ?>
                                <span class="uf-chip"><?= Html::encode($role && $role->description ? $role->description : $roleName) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="uf-card__lead">Роли назначает администратор с правом управления ролями.</p>
                    <div class="uf-roles-locked">
                        <?php if ($assigned): ?>
                            <?php foreach ($assigned as $roleName): ?>
                                <?php $role = $authManager->getRole($roleName); ?>
                                <span class="uf-chip"><?= Html::encode($role && $role->description ? $role->description : $roleName) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="uf-hint">Ролей пока нет.</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <?php $mainContent = ob_get_clean(); ?>

    <?php if (isset($maps) && !empty($maps)): ?>

        <?= $mainContent ?>

        <?php foreach ($maps as $map): ?>
            <div class="widget-content-inner wg-box" style="display: none;">
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

    <div class="uf-actions">
        <button class="tf-button w180" type="submit"><?= $model->isNewRecord ? 'Создать' : 'Сохранить' ?></button>
        <a class="uf-actions__cancel" href="<?= Url::to(['index']) ?>">Отмена</a>
    </div>
<?php ActiveForm::end() ?>
