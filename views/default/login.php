<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */

/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Вход в систему';
?>

<div class="wrap-login-page">
    <div class="flex-grow flex flex-column justify-center gap30">
        <a href="index.html" id="site-logo-inner">

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
                <?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>
            </fieldset>

            <fieldset class="password">
                <?= $form->field($model, 'password')->passwordInput() ?>
            </fieldset>


            <div class="flex justify-between items-center">
                <div class="flex gap10">
                    <input class="" type="checkbox" id="signed" name="LoginForm[rememberMe]">
                    <label class="body-text" for="signed">запомнить меня</label>
                </div>
            </div>

            <?= Html::submitButton('Войти', ['class' => 'tf-button w-full', 'name' => 'login-button']) ?>

            <?php ActiveForm::end(); ?>

        </div>
    </div>
    <div class="text-tiny">KeyPage.ru - <?= date('Y') ?></div>
</div>