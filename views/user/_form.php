<?php
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */

$this->registerJsFile('/web/component/fileuploader/dist/jquery.fileuploader.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('/web/component/fileuploader/js/avatar.js', ['depends' => [\yii\web\JqueryAsset::class]]);

$this->registerCssFile('/web/component/fileuploader/dist/jquery.fileuploader.min.css');
$this->registerCssFile('/web/component/fileuploader/dist/font/font-fileuploader.css');
$this->registerCssFile('/web/component/fileuploader/css/jquery.fileuploader-theme-avatar.css');

$this->registerCss('.fileuploader {max-width: 180px;width: 100%;height: unset;margin: 0 auto;aspect-ratio: 1 / 1;}');
$this->registerCss('.alt-fileuploader-input {background-color: white !important;padding: 4px 12px !important;}');
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
                ]) ?>
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
        </div>
    </div>

    <div class="bot">
        <button class="tf-button w180" type="submit">Сохранить</button>
    </div>
<?php ActiveForm::end() ?>

<script>
    document.querySelector('.generate-pass')?.addEventListener('click', function () {
        const passwordInput = document.querySelector('.password-input');
        const showPassBtn = document.querySelector('.show-pass');

        // Генерируем пароль
        const newPassword = generatePassword(16);
        passwordInput.value = newPassword;

        // Если текущий тип — password, имитируем клик по кнопке "показать"
        if (passwordInput.type === 'password') {
            showPassBtn?.click(); // Это вызовет существующий обработчик
        }
    });

    // Функция для генерации случайного пароля
    function generatePassword(length) {
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~`|}{[]:;?><,./-=";
        let password = "";
        for (let i = 0, n = charset.length; i < length; ++i) {
            password += charset.charAt(Math.floor(Math.random() * n));
        }
        return password;
    }
</script>
