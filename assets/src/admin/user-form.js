/*
 * Форма пользователя админки: способ входа, подключение приложения-аутентификатора, пароль, вкладки.
 * Исходник; в пакет попадает собранный assets/js/custom.user.form.min.js (sh assets/src/build.sh).
 *
 * QR-код рисуется здесь, в браузере: ссылка otpauth:// с секретом никуда не отправляется.
 */
import qrcode from '../lib/qrcode-generator.mjs';

(function () {
    'use strict';

    var form = document.querySelector('.form-add-new-user');
    if (!form) {
        return;
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function show(node, visible) {
        if (node) {
            node.hidden = !visible;
        }
    }

    // --- Вкладки дополнительных разделов профиля ---

    var tabs = Array.prototype.slice.call(document.querySelectorAll('.widget-menu-tab .item-title'));
    var panes = Array.prototype.slice.call(form.querySelectorAll('.widget-content-inner'));
    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (other) {
                other.classList.toggle('active', other === tab);
            });
            panes.forEach(function (pane, paneIndex) {
                pane.classList.toggle('active', paneIndex === index);
                pane.style.display = paneIndex === index ? '' : 'none';
            });
        });
    });

    // --- Пароль ---

    var passwordInput = form.querySelector('.password-input');
    var generateBtn = form.querySelector('.generate-pass');

    /** Случайный пароль из криптографического генератора браузера (не Math.random). */
    function generatePassword(length) {
        var charset = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*-_=+?';
        var limit = 256 - (256 % charset.length);
        var result = '';
        var buffer = new Uint8Array(length * 2);
        while (result.length < length) {
            window.crypto.getRandomValues(buffer);
            for (var i = 0; i < buffer.length && result.length < length; i++) {
                // Байты за пределом отбрасываются: иначе первые знаки набора выпадали бы чаще
                if (buffer[i] < limit) {
                    result += charset.charAt(buffer[i] % charset.length);
                }
            }
        }
        return result;
    }

    if (generateBtn && passwordInput) {
        generateBtn.addEventListener('click', function () {
            passwordInput.value = generatePassword(16);
            if (passwordInput.type === 'password') {
                var showBtn = form.querySelector('.show-pass');
                if (showBtn) {
                    showBtn.click();
                }
            }
            passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    // --- Способ входа ---

    var auth = byId('uf-auth');
    if (!auth) {
        return;
    }

    var methods = Array.prototype.slice.call(auth.querySelectorAll('input[name$="[auth_type]"]'));
    var mfaBlock = byId('uf-mfa');
    var mfaConnected = byId('uf-mfa-connected');
    var mfaSetup = byId('uf-mfa-setup');
    var mfaReset = byId('uf-mfa-reset');
    var mfaResetBtn = byId('uf-mfa-reset-btn');
    var mfaResetCancel = byId('uf-mfa-reset-cancel');
    var mfaCode = byId('uf-mfa-code');
    var passwordField = byId('field-password');
    var noPasswordNote = byId('uf-nopass-note');
    var qrBox = byId('uf-mfa-qr');
    var keyBox = byId('uf-mfa-key');
    var copyBtn = byId('uf-mfa-copy');
    var usernameInput = form.querySelector('input[name$="[username]"]');

    function authType() {
        var checked = methods.filter(function (input) {
            return input.checked;
        })[0];
        return checked ? checked.value : '0';
    }

    function isReset() {
        return !!mfaReset && mfaReset.value === '1';
    }

    function refresh() {
        var type = authType();
        var usesCode = type !== '0';
        var usesPassword = type !== '2';
        var setupVisible = usesCode && (!mfaConnected || isReset());

        methods.forEach(function (input) {
            var card = input.closest('.uf-method');
            if (card) {
                card.classList.toggle('is-active', input.checked);
            }
        });

        show(mfaBlock, usesCode);
        show(mfaConnected, usesCode && !isReset());
        show(mfaSetup, setupVisible);
        show(mfaResetCancel ? mfaResetCancel.closest('.uf-note') : null, isReset());
        show(passwordField, usesPassword);
        show(noPasswordNote, !usesPassword);

        // Скрытые поля не отправляются: сервер не получит ни код, ни пароль «на всякий случай»
        if (mfaCode) {
            mfaCode.disabled = !setupVisible;
        }
        if (passwordInput) {
            passwordInput.disabled = !usesPassword;
        }
        if (setupVisible) {
            drawQr();
        }
    }

    methods.forEach(function (input) {
        input.addEventListener('change', refresh);
    });

    if (mfaResetBtn && mfaReset) {
        mfaResetBtn.addEventListener('click', function () {
            mfaReset.value = '1';
            refresh();
            if (mfaCode) {
                mfaCode.focus();
            }
        });
    }
    if (mfaResetCancel && mfaReset) {
        mfaResetCancel.addEventListener('click', function () {
            mfaReset.value = '0';
            if (mfaCode) {
                mfaCode.value = '';
            }
            refresh();
        });
    }

    if (mfaCode) {
        mfaCode.addEventListener('input', function () {
            mfaCode.value = mfaCode.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    // --- QR-код и ключ ---

    var drawnFor = null;

    function otpauthUrl() {
        var secret = mfaSetup.getAttribute('data-mfa-secret') || '';
        var issuer = mfaSetup.getAttribute('data-mfa-issuer') || window.location.hostname;
        var account = (usernameInput && usernameInput.value.trim()) || mfaSetup.getAttribute('data-mfa-account') || 'user';
        return 'otpauth://totp/' + encodeURIComponent(issuer) + ':' + encodeURIComponent(account)
            + '?secret=' + secret + '&issuer=' + encodeURIComponent(issuer);
    }

    function drawQr() {
        if (!qrBox || !mfaSetup) {
            return;
        }
        var url = otpauthUrl();
        if (url === drawnFor) {
            return;
        }
        drawnFor = url;

        var qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();

        var count = qr.getModuleCount();
        // Поля вокруг кода — 4 модуля, как требует стандарт: без них часть сканеров код не видит
        var quiet = 4;
        var size = count + quiet * 2;
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
        svg.setAttribute('shape-rendering', 'crispEdges');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'QR-код для приложения-аутентификатора');

        var background = document.createElementNS(ns, 'rect');
        background.setAttribute('width', size);
        background.setAttribute('height', size);
        background.setAttribute('fill', '#fff');
        svg.appendChild(background);

        var path = '';
        for (var row = 0; row < count; row++) {
            for (var col = 0; col < count; col++) {
                if (qr.isDark(row, col)) {
                    path += 'M' + (col + quiet) + ' ' + (row + quiet) + 'h1v1h-1z';
                }
            }
        }
        var modules = document.createElementNS(ns, 'path');
        modules.setAttribute('d', path);
        modules.setAttribute('fill', '#111');
        svg.appendChild(modules);

        while (qrBox.firstChild) {
            qrBox.removeChild(qrBox.firstChild);
        }
        qrBox.appendChild(svg);
    }

    if (usernameInput) {
        usernameInput.addEventListener('change', function () {
            if (mfaSetup && !mfaSetup.hidden) {
                drawQr();
            }
        });
    }

    if (copyBtn && keyBox) {
        copyBtn.addEventListener('click', function () {
            var key = (mfaSetup.getAttribute('data-mfa-secret') || '');
            var done = function () {
                var label = copyBtn.textContent;
                copyBtn.textContent = 'Скопировано';
                setTimeout(function () {
                    copyBtn.textContent = label;
                }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(key).then(done, function () {
                    selectKey();
                });
            } else {
                selectKey();
            }
        });
    }

    /** Буфер обмена недоступен (http без localhost): выделяем ключ, чтобы скопировать вручную. */
    function selectKey() {
        var range = document.createRange();
        range.selectNodeContents(keyBox);
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    refresh();
})();
