/*
 * Вход в админку по шагам: логин → пароль → код из приложения.
 * Исходник; в пакет попадает собранный assets/js/login.min.js (sh assets/src/build.sh).
 *
 * Шаг приходит с сервера (window.loginConfig.initialState.step). Если пароль уже проверен
 * (passwordConfirmed), в форме нет полей логина и пароля — только шесть цифр.
 */
(function () {
    'use strict';

    var config = window.loginConfig || {};
    var initial = config.initialState || {};

    var form = document.getElementById('login-form');
    if (!form) {
        return;
    }

    var el = {
        stepLogin: document.getElementById('step-login'),
        stepPassword: document.getElementById('step-password'),
        stepOtp: document.getElementById('step-otp'),
        next: document.getElementById('next-btn'),
        submit: document.getElementById('submit-btn'),
        submitText: document.getElementById('submit-text'),
        back: document.getElementById('back-option'),
        backBtn: document.getElementById('back-btn'),
        title: document.getElementById('greeting-title'),
        sub: document.getElementById('greeting-subtitle'),
        username: document.getElementById('username'),
        password: document.getElementById('password'),
        blur: document.getElementById('blur-toggle'),
        otpHidden: document.getElementById('full_otp_code'),
        error: document.getElementById('error-alert'),
        errorText: document.getElementById('error-text')
    };
    var otpInputs = Array.prototype.slice.call(document.querySelectorAll('.otp-input'));

    var step = 'login';
    var sending = false;
    var nextBtnHTML = el.next ? el.next.innerHTML : '';
    var submitBtnHTML = el.submit ? el.submit.innerHTML : '';
    var spinnerHTML = '<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">'
        + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
        + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>'
        + '</svg>';

    function toggle(node, visible) {
        if (node) {
            node.classList.toggle('hidden', !visible);
        }
    }

    function showError(message) {
        if (!message || !el.error) {
            return;
        }
        el.errorText.textContent = message;
        el.error.classList.remove('hidden');
        el.error.classList.add('animate-shake');
        setTimeout(function () {
            el.error.classList.remove('animate-shake');
        }, 500);
    }

    function hideError() {
        if (el.error) {
            el.error.classList.add('hidden');
        }
    }

    function greeting() {
        var hour = new Date().getHours();
        if (hour < 5) return 'Доброй ночи';
        if (hour < 12) return 'Доброе утро';
        if (hour < 18) return 'Добрый день';
        return 'Добрый вечер';
    }

    /**
     * Показывает один шаг. Поле, которого на шаге нет, отключается: менеджер паролей
     * не подставит пароль туда, где ждут только код, и наоборот.
     */
    function showStep(name, username) {
        step = name;
        toggle(el.stepLogin, name === 'login');
        toggle(el.stepPassword, name === 'password');
        toggle(el.stepOtp, name === 'otp');
        toggle(el.next, name === 'login');
        toggle(el.submit, name !== 'login');
        toggle(el.back, name !== 'login');

        if (el.password) {
            el.password.disabled = name !== 'password';
        }
        if (el.otpHidden) {
            el.otpHidden.disabled = name !== 'otp';
        }

        if (name === 'login') {
            el.title.textContent = greeting();
            el.sub.textContent = 'Введите логин, чтобы продолжить';
            if (el.next) {
                el.next.innerHTML = nextBtnHTML;
            }
            if (el.username) {
                el.username.focus();
            }
        } else if (name === 'password') {
            el.title.textContent = 'Привет, ' + username;
            el.sub.textContent = 'Введите пароль';
            el.submitText.textContent = 'Войти';
            el.password.focus();
        } else {
            el.title.textContent = initial.passwordConfirmed ? 'Подтвердите вход' : 'Привет, ' + username;
            el.sub.textContent = 'Введите код из приложения';
            el.submitText.textContent = 'Подтвердить';
            clearOtp();
            updateSubmitState();
            if (otpInputs.length) {
                otpInputs[0].focus();
            }
        }
    }

    function checkUser() {
        if (sending) {
            return;
        }
        hideError();
        var username = el.username.value.trim();
        if (!username) {
            showError('Введите логин.');
            el.username.focus();
            return;
        }

        sending = true;
        el.next.innerHTML = spinnerHTML;

        fetch(config.checkUserUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': config.csrfToken
            },
            body: 'username=' + encodeURIComponent(username)
                + '&' + encodeURIComponent(config.csrfParam) + '=' + encodeURIComponent(config.csrfToken)
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                sending = false;
                if (data && data.success) {
                    el.username.value = data.username || username;
                    showStep(data.type === 'otp' ? 'otp' : 'password', el.username.value);
                } else {
                    el.next.innerHTML = nextBtnHTML;
                    showError((data && data.message) || 'Не удалось проверить логин.');
                }
            })
            .catch(function () {
                sending = false;
                el.next.innerHTML = nextBtnHTML;
                showError('Нет связи с сервером. Попробуйте ещё раз.');
            });
    }

    // --- Шесть цифр ---

    function otpValue() {
        return otpInputs.map(function (input) {
            return input.value;
        }).join('');
    }

    function syncOtp() {
        if (el.otpHidden) {
            el.otpHidden.value = otpValue();
        }
        updateSubmitState();
    }

    function clearOtp() {
        otpInputs.forEach(function (input) {
            input.value = '';
        });
        syncOtp();
    }

    function updateSubmitState() {
        if (!el.submit) {
            return;
        }
        var waiting = step === 'otp' && otpValue().length < otpInputs.length;
        el.submit.classList.toggle('lg-waiting', waiting);
    }

    /** Раскладывает цифры по клеткам начиная с index; так работают вставка и автоподстановка кода. */
    function fillFrom(index, digits) {
        for (var i = 0; i < digits.length && index + i < otpInputs.length; i++) {
            otpInputs[index + i].value = digits.charAt(i);
        }
        var nextIndex = Math.min(index + digits.length, otpInputs.length - 1);
        otpInputs[nextIndex].focus();
        syncOtp();
        if (otpValue().length === otpInputs.length) {
            submitForm();
        }
    }

    function onOtpInput(event) {
        var input = event.target;
        var index = otpInputs.indexOf(input);
        var digits = input.value.replace(/\D/g, '');
        hideError();
        otpInputs.forEach(function (node) {
            node.classList.remove('is-error');
        });

        if (digits.length === 0) {
            input.value = '';
            syncOtp();
            return;
        }
        // Одна цифра — обычный набор; несколько — вставка или автоподстановка кода из SMS/приложения
        input.value = '';
        fillFrom(digits.length >= otpInputs.length ? 0 : index, digits.slice(0, otpInputs.length));
    }

    function onOtpKeydown(event) {
        var index = otpInputs.indexOf(event.target);
        if (event.key === 'Backspace' && event.target.value === '' && index > 0) {
            otpInputs[index - 1].value = '';
            otpInputs[index - 1].focus();
            syncOtp();
            event.preventDefault();
        } else if (event.key === 'ArrowLeft' && index > 0) {
            otpInputs[index - 1].focus();
            event.preventDefault();
        } else if (event.key === 'ArrowRight' && index < otpInputs.length - 1) {
            otpInputs[index + 1].focus();
            event.preventDefault();
        }
    }

    function onOtpPaste(event) {
        var text = (event.clipboardData || window.clipboardData).getData('text') || '';
        var digits = text.replace(/\D/g, '').slice(0, otpInputs.length);
        event.preventDefault();
        if (digits) {
            hideError();
            clearOtp();
            fillFrom(0, digits);
        }
    }

    function submitForm() {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(el.submit);
        } else {
            el.submit.click();
        }
    }

    function onSubmit(event) {
        if (sending) {
            event.preventDefault();
            return;
        }
        if (step === 'login') {
            event.preventDefault();
            checkUser();
            return;
        }
        if (step === 'password' && !el.password.value) {
            event.preventDefault();
            showError('Введите пароль.');
            el.password.focus();
            return;
        }
        if (step === 'otp') {
            syncOtp();
            if (otpValue().length < otpInputs.length) {
                event.preventDefault();
                showError('Введите все 6 цифр кода.');
                (otpInputs[otpValue().length] || otpInputs[0]).focus();
                return;
            }
        }
        sending = true;
        el.submit.innerHTML = spinnerHTML + '<span>Проверяем…</span>';
        el.submit.classList.add('opacity-75', 'cursor-not-allowed');
    }

    function resetFlow() {
        hideError();
        if (el.password) {
            el.password.value = '';
        }
        showStep('login');
    }

    function toggleBlur() {
        if (!el.username) {
            return;
        }
        el.username.style.filter = el.username.style.filter ? '' : (el.username.value ? 'blur(4px)' : '');
    }

    function init() {
        form.addEventListener('submit', onSubmit);
        if (el.next) {
            el.next.addEventListener('click', checkUser);
        }
        if (el.backBtn) {
            el.backBtn.addEventListener('click', resetFlow);
        }
        if (el.blur) {
            el.blur.addEventListener('click', toggleBlur);
        }
        if (el.username) {
            el.username.addEventListener('input', hideError);
        }
        if (el.password) {
            el.password.addEventListener('input', hideError);
        }
        otpInputs.forEach(function (input) {
            input.addEventListener('input', onOtpInput);
            input.addEventListener('keydown', onOtpKeydown);
            input.addEventListener('paste', onOtpPaste);
            input.addEventListener('focus', function () {
                input.select();
            });
        });

        // Страница из кэша «назад»: кнопка не должна остаться в состоянии «Проверяем…»
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                sending = false;
                if (el.submit) {
                    el.submit.innerHTML = submitBtnHTML;
                    el.submit.classList.remove('opacity-75', 'cursor-not-allowed');
                    el.submitText = document.getElementById('submit-text');
                }
            }
        });

        var first = initial.step === 'otp' || initial.step === 'password' ? initial.step : 'login';
        if (first !== 'login' && !initial.passwordConfirmed && !initial.username) {
            first = 'login';
        }
        showStep(first, initial.username || '');

        if (initial.hasErrors) {
            showError(initial.errorMessage);
            if (first === 'otp') {
                otpInputs.forEach(function (node) {
                    node.classList.add('is-error');
                });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
