<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */

/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

\Mitisk\Yii2Admin\assets\LoginAsset::register($this);

$this->title = 'Вход в систему';
?>

    <div class="wrap-login-page">
        <div class="flex-grow flex flex-column justify-center gap30">
            <a href="#" id="site-logo-inner">

            </a>
            <div class="login-box">
                <div>
                    <h3>Вход</h3>
                    <div class="body-text">Для входа в панель управления, введите данные</div>
                </div>

                <?php $form = ActiveForm::begin([
                    'id' => 'login-form',
                    'fieldConfig' => [
                        'template' => "{label}\n{input}\n{error}",
                        'labelOptions' => ['class' => 'body-title mb-10'],
                        'inputOptions' => ['class' => 'col-lg-3 form-control'],
                        'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
                    ],
                    'options' => ['class' => 'form-login flex flex-column gap24']
                ]); ?>

                <fieldset class="email">
                    <?= $form->field($model, 'username')->textInput(['autofocus' => !$model->username, 'readonly' => !!$model->username]) ?>
                </fieldset>

                <?php if ($model->username):  ?>
                    <?php if ($model->authType == $model::MFA_PASSWORD) : ?>
                        <?php if (!$model->password) : ?>

                            <fieldset class="password">
                                <?= $form->field($model, 'password')->passwordInput() ?>
                            </fieldset>

                        <?php else: ?>

                            <?= $form->field($model, 'password')->hiddenInput()->label(false) ?>

                            <fieldset class="password">
                                <label class="body-title" for="otp_target">Временный код</label>
                                <div id="otp_target"></div>
                            </fieldset>

                        <?php endif; ?>

                    <?php elseif ($model->authType == $model::MFA) : ?>
                        <fieldset class="password <?= $form->field($model, 'mfaCode')->error() ? 'has-error' : '' ?>">
                            <label class="body-title" for="otp_target">Временный код</label>
                            <div id="otp_target"></div>
                            <?= Html::error($model, 'mfaCode') ?>
                        </fieldset>
                    <?php else : ?>
                        <fieldset class="password">
                            <?= $form->field($model, 'password')->passwordInput() ?>
                        </fieldset>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="flex justify-between items-center" style="display: none">
                    <div class="flex gap10">
                        <input class="" type="checkbox" id="signed" name="LoginForm[rememberMe]" checked>
                        <label class="body-text" for="signed">запомнить меня</label>
                    </div>
                </div>

                <?php if ($model->username):  ?>

                    <?= Html::submitButton('Войти', ['class' => 'tf-button w-full', 'name' => 'login-button']) ?>

                    <a href="/admin/login/" class="tf-button style-2 w-full">Войти под другим пользователем</a>
                <?php else: ?>
                    <?= Html::submitButton('Продолжить', ['class' => 'tf-button w-full', 'name' => 'login-button']) ?>
                <?php endif; ?>

                <?php ActiveForm::end(); ?>

            </div>
        </div>
        <div class="text-tiny">KeyPage.ru - <?= date('Y') ?></div>
    </div>

<?php
$this->registerJs("
    $('#otp_target').otpdesigner({});
    $('#otp_target').find('input[type=\"hidden\"]').attr('name', '" . Html::getInputName($model, "mfaCode") . "');
");

?>