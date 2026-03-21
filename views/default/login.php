<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use Mitisk\Yii2Admin\assets\LoginAsset;

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\LoginForm $model */

$this->title = 'Вход в систему | Admin Panel';

// Register login asset bundle (CSS + JS)
$loginBundle = LoginAsset::register($this);

// LoginAsset и AppAsset используют один sourcePath — берём baseUrl из LoginAsset
$loginImageUrl = $loginBundle->baseUrl . '/img/login.png';

// Pass PHP config to JS
$initialState = Json::encode([
    'username' => $model->username,
    'authType' => $model->authType,
    'hasErrors' => $model->hasErrors(),
    'errorMessage' => $model->hasErrors() ? implode(' ', $model->getFirstErrors()) : '',
]);
$checkUserUrl = Url::to(['/admin/default/check-user']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;

$jsConfig = "window.loginConfig = {
    initialState: {$initialState},
    authTypes: { 0: 'password', 1: 'mixed', 2: 'otp' },
    checkUserUrl: " . Json::encode($checkUserUrl) . ",
    csrfParam: " . Json::encode($csrfParam) . ",
    csrfToken: " . Json::encode($csrfToken) . "
};";

$this->registerJs($jsConfig, \yii\web\View::POS_HEAD);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::encode($this->title) ?></title>
    <?= Html::csrfMetaTags() ?>
    <?php $this->head() ?>
</head>
<body class="login-page">
<?php $this->beginBody() ?>

    <!-- Фоновые декоративные элементы (Abstract shapes) -->
    <div class="absolute top-[-10%] left-[-5%] w-96 h-96 bg-blue-100 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob"></div>
    <div class="absolute top-[-10%] right-[-5%] w-96 h-96 bg-purple-100 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000"></div>
    <div class="absolute bottom-[-10%] left-[20%] w-96 h-96 bg-pink-100 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000"></div>

    <!-- Основной контейнер (Card) -->
    <div class="bg-white w-full max-w-[1000px] h-[600px] rounded-3xl shadow-2xl flex overflow-hidden z-10 relative animate-fade-up">

        <!-- Левая часть: Форма -->
        <div class="w-full md:w-1/2 p-10 flex flex-col justify-between relative">

            <!-- Хедер: Лого и Статус -->
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">
                        A
                    </div>
                    <span class="font-semibold text-slate-700 tracking-tight">Yii<span style="color: #2563eb;">:</span>admin</span>
                </div>

                <!-- Индикатор безопасности -->
                <div class="flex items-center gap-1.5 px-3 py-1 bg-green-50 rounded-full border border-green-100">
                    <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-xs font-medium text-green-700">Protected</span>
                </div>
            </div>

            <!-- Основной блок формы -->
            <div class="flex flex-col justify-center h-full max-w-sm mx-auto w-full" id="auth-container">

                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-slate-800 mb-2" id="greeting-title">Добро пожаловать</h1>
                    <p class="text-slate-500 text-sm" id="greeting-subtitle">Введите идентификатор для доступа</p>
                </div>

                <!-- Блок ошибки (скрыт по умолчанию) -->
                <div id="error-alert" class="hidden flex items-start p-3 mb-5 text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl animate-fade-up" role="alert">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <span class="font-bold block mb-0.5">Ошибка входа</span>
                        <span id="error-text">Неверные учетные данные.</span>
                    </div>
                </div>

                <form id="login-form" action="<?= Url::to(['/admin/default/login']) ?>" method="POST" onsubmit="handleAuth(event)" class="space-y-5">

                    <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">

                    <!-- STEP 1: LOGIN -->
                    <div id="step-login" class="block">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Логин / Email</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input type="text" id="username" name="LoginForm[username]" value="<?= Html::encode($model->username) ?>" class="w-full pl-10 pr-10 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 input-transition placeholder-slate-400 font-medium" placeholder="username" autocomplete="off" oninput="hideError()">

                            <!-- Privacy Blur Button -->
                            <button type="button" onclick="toggleBlur()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer" title="Скрыть логин">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: PASSWORD (Hidden by default) -->
                    <div id="step-password" class="hidden animate-fade-up">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Пароль</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" id="password" name="LoginForm[password]" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 input-transition placeholder-slate-400 font-medium" placeholder="••••••••" oninput="hideError()">
                        </div>
                    </div>

                    <!-- STEP 3: OTP (Hidden by default) -->
                    <div id="step-otp" class="hidden animate-fade-up">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5 ml-1 flex justify-between">
                            <span>Код подтверждения</span>
                        </label>
                        <div class="flex gap-2 justify-between" id="otp-container">
                            <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl font-bold bg-white border border-slate-200 rounded-lg focus:outline-none transition-all text-slate-700" oninput="moveToNext(this, 'otp2'); hideError()" id="otp1">
                            <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl font-bold bg-white border border-slate-200 rounded-lg focus:outline-none transition-all text-slate-700" oninput="moveToNext(this, 'otp3'); hideError()" onkeydown="moveToPrev(event, 'otp1')" id="otp2">
                            <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl font-bold bg-white border border-slate-200 rounded-lg focus:outline-none transition-all text-slate-700" oninput="moveToNext(this, 'otp4'); hideError()" onkeydown="moveToPrev(event, 'otp2')" id="otp3">
                            <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl font-bold bg-white border border-slate-200 rounded-lg focus:outline-none transition-all text-slate-700" oninput="moveToNext(this, 'otp5'); hideError()" onkeydown="moveToPrev(event, 'otp3')" id="otp4">
                            <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl font-bold bg-white border border-slate-200 rounded-lg focus:outline-none transition-all text-slate-700" oninput="moveToNext(this, 'otp6'); hideError()" onkeydown="moveToPrev(event, 'otp4')" id="otp5">
                            <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl font-bold bg-white border border-slate-200 rounded-lg focus:outline-none transition-all text-slate-700" oninput="moveToNext(this, 'submit-btn'); hideError()" onkeydown="moveToPrev(event, 'otp5')" id="otp6">
                        </div>
                        <input type="hidden" name="LoginForm[mfaCode]" id="full_otp_code">
                    </div>

                    <!-- Actions -->
                    <div class="pt-2">
                        <button type="button" id="next-btn" onclick="checkUser()" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-600/30 transition-all transform active:scale-95 flex items-center justify-center gap-2">
                            <span>Продолжить</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>

                        <button type="submit" id="submit-btn" class="hidden w-full py-3 px-4 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-xl shadow-lg shadow-slate-800/20 transition-all transform active:scale-95 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Войти в систему</span>
                        </button>
                    </div>

                    <!-- Reset Flow (Back button) -->
                    <div id="back-option" class="hidden text-center">
                        <button type="button" onclick="resetFlow()" class="text-sm text-slate-400 hover:text-slate-600 font-medium transition-colors">
                            ← Вернуться к вводу логина
                        </button>
                    </div>

                </form>
            </div>

            <!-- Футер: Версия -->
            <div class="flex justify-between items-end text-[10px] text-slate-400 font-medium uppercase tracking-widest">
                <span>stable</span>
                <span>Secure SSL</span>
            </div>
        </div>

        <!-- Правая часть: Визуал -->
        <div class="hidden md:block w-1/2 bg-slate-50 relative overflow-hidden">
            <img src="<?= $loginImageUrl ?>"
                 class="absolute inset-0 w-full h-full object-cover opacity-90"
                 alt="Office abstract">

            <!-- Оверлей градиент -->
            <div class="absolute inset-0 bg-gradient-to-tr from-blue-600/20 to-purple-600/20 mix-blend-overlay"></div>

            <!-- Информационная карточка поверх изображения -->
            <div class="absolute bottom-10 left-10 right-10 glass-panel p-6 rounded-2xl border border-white/40 shadow-lg animate-fade-up" style="animation-delay: 0.2s;">
                <h3 class="text-lg font-bold text-slate-800 mb-1">Центр управления</h3>
                <p class="text-slate-600 text-sm leading-relaxed">
                    Доступ разрешен только авторизованным пользователям.
                    Все действия в системе логируются.
                </p>
            </div>
        </div>
    </div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
