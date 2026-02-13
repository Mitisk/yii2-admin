<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\LoginForm $model */

$this->title = 'Вход в систему | Admin Panel';

// Prepare initial state for JS
$initialState = [
    'username' => $model->username,
    'authType' => $model->authType,
    'hasErrors' => $model->hasErrors(),
    'errorMessage' => $model->hasErrors() ? implode(' ', $model->getFirstErrors()) : '',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::encode($this->title) ?></title>
    <?= Html::csrfMetaTags() ?>
    <!-- Подключаем Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Подключаем шрифт Inter для профессионального вида -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F3F4F6; /* Soft gray background */
        }
        
        /* Плавные переходы для полей */
        .input-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Анимация появления блоков */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up {
            animation: fadeUp 0.4s ease-out forwards;
        }
        
        /* Анимация тряски для ошибок */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
            20%, 40%, 60%, 80% { transform: translateX(4px); }
        }
        .animate-shake {
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }

        /* Стили для OTP инпутов */
        .otp-input:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* Эффект матового стекла для декоративного элемента */
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
        
        /* Скрытие стандартного глаза пароля в IE/Edge */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563EB', // Blue-600
                        secondary: '#64748B', // Slate-500
                        success: '#10B981',
                        surface: '#FFFFFF',
                    }
                }
            }
        }
    </script>
</head>
<body class="h-screen w-full flex items-center justify-center overflow-hidden relative">

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
                    <span class="font-semibold text-slate-700 tracking-tight">Yii<span style="color: rgb(37 99 235 / var(--tw-bg-opacity, 1))">:</span>admin</span>
                </div>
                
                <!-- Индикатор безопасности (Unique Feature) -->
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
                    <svg class="w-5 h-5 inline mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
                            
                            <!-- Privacy Blur Button (Unique Feature) -->
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
                            <!-- JS генерирует поля или статика -->
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

        <!-- Правая часть: Визуал (Placeholder for branding) -->
        <div class="hidden md:block w-1/2 bg-slate-50 relative overflow-hidden">
            <!-- Фоновое изображение (Абстракция) -->
            <img src="https://images.unsplash.com/photo-1497215728101-856f4ea42174?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80" 
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

    <!-- Логика работы формы -->
    <script>
        const initialState = <?= json_encode($initialState) ?>;
        const authTypes = {
            0: 'password', // LoginForm::PASSWORD
            1: 'mixed',    // LoginForm::MFA_PASSWORD
            2: 'otp'       // LoginForm::MFA
        };
        const checkUserUrl = '<?= Url::to(['/admin/default/check-user']) ?>';
        const csrfParam = '<?= Yii::$app->request->csrfParam ?>';
        const csrfToken = '<?= Yii::$app->request->csrfToken ?>';

        const steps = {
            login: document.getElementById('step-login'),
            password: document.getElementById('step-password'),
            otp: document.getElementById('step-otp')
        };

        const buttons = {
            next: document.getElementById('next-btn'),
            submit: document.getElementById('submit-btn'),
            back: document.getElementById('back-option')
        };

        const titles = {
            main: document.getElementById('greeting-title'),
            sub: document.getElementById('greeting-subtitle')
        };

        // Управление ошибками
        function showError(message) {
            if (!message) return;
            const errorBlock = document.getElementById('error-alert');
            const errorText = document.getElementById('error-text');
            errorText.textContent = message;
            errorBlock.classList.remove('hidden');
            errorBlock.classList.add('animate-shake');
            
            // Убираем класс анимации после завершения
            setTimeout(() => {
                errorBlock.classList.remove('animate-shake');
            }, 500);
        }

        function hideError() {
            document.getElementById('error-alert').classList.add('hidden');
        }

        // 1. Проверка пользователя
        function checkUser() {
            hideError();
            const loginInput = document.getElementById('username');
            const username = loginInput.value.toLowerCase().trim();

            if (!username) {
                showError('Пожалуйста, введите логин.');
                return;
            }

            // Имитация загрузки
            buttons.next.innerHTML = `<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
            
            fetch(checkUserUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: 'username=' + encodeURIComponent(username) + '&' + csrfParam + '=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const scenario = { type: data.type };
                     transitionToNextStep(data.username, scenario);
                     // Set the username again just in case (e.g. casing fix)
                     loginInput.value = data.username;
                } else {
                     showError(data.message || 'Ошибка проверки пользователя');
                     buttons.next.innerHTML = `<span>Продолжить</span><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>`;
                }
            })
            .catch(error => {
                showError('Ошибка сети: ' + error.message);
                buttons.next.innerHTML = `<span>Продолжить</span><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>`;
            });
        }

        // 2. Переход к следующему шагу
        function transitionToNextStep(username, scenario) {
            // Скрываем поле логина, показываем его в виде текста
            steps.login.classList.add('hidden');
            
            // Обновляем заголовок
            titles.main.innerText = `Привет, ${username}`;
            titles.sub.innerText = 'Подтвердите вашу личность';

            buttons.next.classList.add('hidden');
            buttons.submit.classList.remove('hidden');
            buttons.back.classList.remove('hidden');

            // Логика отображения полей
            if (scenario.type === 'password') {
                steps.password.classList.remove('hidden');
                document.getElementById('password').focus();
            } else if (scenario.type === 'otp') {
                steps.otp.classList.remove('hidden');
                document.getElementById('otp1').focus();
            } else if (scenario.type === 'mixed') {
                steps.password.classList.remove('hidden');
                steps.otp.classList.remove('hidden');
                document.getElementById('password').focus();
            }
        }

        // 3. Сброс к началу
        function resetFlow() {
            hideError();
            steps.login.classList.remove('hidden');
            steps.password.classList.add('hidden');
            steps.otp.classList.add('hidden');
            
            buttons.next.classList.remove('hidden');
            buttons.next.innerHTML = `<span>Продолжить</span><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>`;
            
            buttons.submit.classList.add('hidden');
            buttons.back.classList.add('hidden');

            titles.main.innerText = 'Добро пожаловать';
            titles.sub.innerText = 'Введите идентификатор для доступа';
            
            document.getElementById('username').focus();
        }

        // 4. Логика OTP инпутов
        function moveToNext(elem, nextFieldID) {
            // 1) Allow only digits
            elem.value = elem.value.replace(/[^0-9]/g, '');

            if (elem.value.length >= elem.maxLength) {
                if(nextFieldID === 'submit-btn') {
                   // 2) Auto-submit
                   collectOtp();
                   document.getElementById('submit-btn').click();
                } else {
                   document.getElementById(nextFieldID).focus();
                }
            }
            collectOtp();
        }

        function moveToPrev(event, prevFieldID) {
            if (event.key === "Backspace" && event.target.value.length === 0) {
                document.getElementById(prevFieldID).focus();
            }
            setTimeout(collectOtp, 0);
        }

        function collectOtp() {
            let code = '';
            document.querySelectorAll('.otp-input').forEach(input => code += input.value);
            document.getElementById('full_otp_code').value = code;
        }

        // 5. Фича: Блюр логина
        function toggleBlur() {
            const input = document.getElementById('username');
            if (input.style.filter === 'blur(4px)') {
                input.style.filter = 'none';
            } else {
                if(input.value) input.style.filter = 'blur(4px)';
            }
        }

        // 6. Обработка отправки формы
        function handleAuth(e) {
            // Не отменяем отправку, если только не хотим делать валидацию перед отправкой
            // e.preventDefault(); 
            
            const btn = document.getElementById('submit-btn');
            
            // Если нужно, можно добавить валидацию
            // ...
            
            btn.innerHTML = 'Проверка...';
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            // Form continues to submit...
        }

        // Динамическое приветствие по времени и начальное состояние
        window.onload = function() {
            const hour = new Date().getHours();
            const title = document.getElementById('greeting-title');
            if (title.innerText === 'Добро пожаловать') {
                 if (hour < 12) title.innerText = "Доброе утро";
                 else if (hour < 18) title.innerText = "Добрый день";
                 else title.innerText = "Добрый вечер";
            }
            
            // Restore state if returning from server error
            if (initialState.username) {
                // We know auth type from PHP
                let scenarioType = authTypes[initialState.authType] || 'password';
                
                // Transition immediately
                transitionToNextStep(initialState.username, { type: scenarioType });
                
                if (initialState.hasErrors) {
                    showError(initialState.errorMessage);
                }
            }
        };
    </script>
</body>
</html>