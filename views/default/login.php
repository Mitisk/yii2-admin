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
var otpContainer = $('#otp_target');
    var form = $('#login-form');

    // Инициализация
    otpContainer.otpdesigner({
        onlyNumbers: true // Обычно полезная настройка для OTP
    });

    // Привязка имени для Yii2
    var hiddenInput = otpContainer.find('input[type=\"hidden\"]');
    hiddenInput.attr('name', '" . Html::getInputName($model, "mfaCode") . "');

    // Автофокус (клик по фейковому инпуту)
    otpContainer.find('.otp-fake-input').first().trigger('click');

    // ЛОГИКА АВТОСАБМИТА
    // Слушаем ввод в реальное (скрытое) поле textarea, которое создает плагин
    otpContainer.find('.realInput').on('input keyup', function() {
        // Берем значение из скрытого input-а, куда плагин дублирует итоговый код
        var code = hiddenInput.val();
        
        // Если длина равна 6
        if (code.length === 6) {
            // Убираем фокус, чтобы не сработало дважды (опционально)
            $(this).blur();
            
            // Отправляем форму
            form.trigger('submit'); 
        }
    });
");

$this->registerCss("
    /* Анимация мигания */
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }

    /* Курсор */
    .otpdesigner__focus__ {
        position: relative;
    }
    
    .otpdesigner__focus__::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 1px; /* Толщина курсора */
        height: 60%; /* Высота курсора относительно поля */
        background-color: #333; /* Цвет курсора */
        animation: blink 1s step-end infinite;
        pointer-events: none; /* Чтобы клик проходил сквозь курсор */
    }
    
    /* Скрываем курсор, если в поле уже есть цифра (опционально, но выглядит аккуратнее) */
    .otpdesigner__focus__ .otp-content:not(:empty) + ::after,
    .otpdesigner__focus__:has(.otp-content:not(:empty))::after {
        display: none;
    }
");