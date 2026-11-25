/**
 * Редактор шаблона письма (/admin/email-template/update/): визуальный редактор, переменные
 * (вставка по клику, поиск в тексте), живой предпросмотр в макете и тестовая отправка.
 * Строки переменных рисует сервер; для переменных, набранных в тексте, — <template> из той же разметки.
 */
(function ($) {
    'use strict';

    var root = document.querySelector('[data-et]');
    if (!root) {
        return;
    }

    var config = JSON.parse(root.getAttribute('data-config') || '{}');
    var form = root.querySelector('form');
    var subject = root.querySelector('[data-et-subject]');
    var body = root.querySelector('[data-et-body]');
    var varsBox = root.querySelector('[data-et-vars]');
    var varsEmpty = root.querySelector('[data-et-vars-empty]');
    var rowTemplate = root.querySelector('[data-et-var-template]');
    var chips = root.querySelector('[data-et-chips]');
    var counter = root.querySelector('[data-et-counter]');
    var iframe = root.querySelector('[data-et-iframe]');
    var frame = root.querySelector('[data-et-frame]');
    var previewSubject = root.querySelector('[data-et-preview-subject]');
    var dirtyBox = root.querySelector('[data-et-dirty]');
    var testEmail = root.querySelector('[data-et-test-email]');
    var testButton = root.querySelector('[data-et-test-send]');
    var testResult = root.querySelector('[data-et-test-result]');

    var SUBJECT_GOOD = 60;
    var editor = null;          // экземпляр Trumbowyg
    var lastTarget = 'body';    // куда вставлять переменную: тема или текст
    var dirty = false;
    var submitting = false;
    var previewTimer = null;
    var previewRequest = 0;

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function csrfParam() {
        var meta = document.querySelector('meta[name="csrf-param"]');
        return meta ? meta.getAttribute('content') : '_csrf';
    }

    function bodyHtml() {
        return editor ? editor.html() : body.value;
    }

    /* ── Переменные ──────────────────────────────────────── */
    function namesIn(text) {
        var found = [];
        var re = /(?:\{\{|%7B%7B)(\w+)(?:\}\}|%7D%7D)/gi;
        var match;
        while ((match = re.exec(text)) !== null) {
            if (match[1] !== 'content' && found.indexOf(match[1]) === -1) {
                found.push(match[1]);
            }
        }
        return found;
    }

    function rows() {
        return Array.prototype.slice.call(varsBox.querySelectorAll('[data-et-var]'));
    }

    function rowOf(name) {
        return varsBox.querySelector('[data-et-var="' + name + '"]');
    }

    function addRow(name) {
        var holder = document.createElement('div');
        holder.innerHTML = rowTemplate.innerHTML.split('__NAME__').join(name);
        var row = holder.firstElementChild;
        varsBox.appendChild(row);
        return row;
    }

    function syncVariables() {
        var used = namesIn(subject.value + ' ' + bodyHtml());

        used.forEach(function (name) {
            if (!rowOf(name)) {
                addRow(name);
            }
        });

        rows().forEach(function (row) {
            var name = row.getAttribute('data-et-var');
            var isUsed = used.indexOf(name) !== -1;
            var fromCode = row.getAttribute('data-code') === '1';
            var state = row.querySelector('[data-et-state]');
            var text;
            var tone = '';

            if (isUsed && config.event && !fromCode) {
                text = 'сайт её не передаёт — в письме будет пусто';
                tone = 'warn';
            } else if (isUsed) {
                text = 'есть в письме';
                tone = 'ok';
            } else {
                text = fromCode ? 'можно вставить' : 'в письме не используется';
            }
            state.textContent = text;
            state.className = 'et-var__state' + (tone ? ' is-' + tone : '');
            row.classList.toggle('is-unused', !isUsed);
            // Убрать можно только лишнюю: не из кода и не из текста
            row.querySelector('[data-et-remove]').hidden = isUsed || fromCode;
        });

        varsEmpty.hidden = rows().length > 0;
        renderChips();
    }

    function renderChips() {
        chips.textContent = '';
        rows().forEach(function (row, index) {
            var name = row.getAttribute('data-et-var');
            if (index === 0) {
                var label = document.createElement('span');
                label.className = 'et-chips__label';
                label.textContent = 'Вставить:';
                chips.appendChild(label);
            }
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'et-chip';
            chip.setAttribute('data-et-insert', name);
            chip.title = row.querySelector('[data-et-desc]').value.trim() || 'Вставить переменную';
            chip.textContent = '{{' + name + '}}';
            chips.appendChild(chip);
        });
    }

    function insertVariable(name) {
        var text = '{{' + name + '}}';

        if (lastTarget === 'subject') {
            insertIntoField(subject, text);
        } else if (editor && editor.$ed.is(':visible')) {
            editor.$ed.trigger('focus');
            editor.restoreRange();
            if (!document.execCommand('insertText', false, text)) {
                editor.html(editor.html() + text);
            }
            editor.saveRange();
            editor.syncTextarea();
        } else {
            // Режим «HTML-код» редактора или редактор не загрузился
            insertIntoField(body, text);
            if (editor) {
                editor.$ta.trigger('input');
            }
        }
        changed();
    }

    function insertIntoField(field, text) {
        var start = field.selectionStart == null ? field.value.length : field.selectionStart;
        var end = field.selectionEnd == null ? field.value.length : field.selectionEnd;
        field.value = field.value.slice(0, start) + text + field.value.slice(end);
        field.focus();
        field.setSelectionRange(start + text.length, start + text.length);
    }

    /* ── Тема ────────────────────────────────────────────── */
    function updateCounter() {
        var length = subject.value.length;
        counter.textContent = length + ' зн.' + (length > SUBJECT_GOOD ? ' — в списке писем тема обрежется' : '');
        counter.classList.toggle('is-long', length > SUBJECT_GOOD);
    }

    /* ── Предпросмотр ────────────────────────────────────── */
    function contentData() {
        var data = new FormData();
        data.append(csrfParam(), csrf());
        data.append('subject', subject.value);
        data.append('body', bodyHtml());
        rows().forEach(function (row) {
            var name = row.getAttribute('data-et-var');
            var html = row.getAttribute('data-html-locked') === '1'
                || !!row.querySelector('[data-et-flag="html"]:checked');
            data.append('params[' + name + '][desc]', row.querySelector('[data-et-desc]').value);
            data.append('params[' + name + '][sample]', row.querySelector('[data-et-sample]').value);
            data.append('params[' + name + '][html]', html ? '1' : '0');
        });
        return data;
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(loadPreview, 500);
    }

    function loadPreview() {
        var request = ++previewRequest;
        frame.classList.add('is-loading');

        fetch(config.previewUrl, {
            method: 'POST',
            body: contentData(),
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            return response.json();
        }).then(function (data) {
            if (request !== previewRequest) {
                return;
            }
            previewSubject.textContent = data.subject || '(без темы)';
            iframe.srcdoc = data.html || '<p style="font:14px sans-serif;color:#888;padding:24px">Текста письма пока нет.</p>';
        }).catch(function () {
            if (request === previewRequest) {
                iframe.srcdoc = '<p style="font:14px sans-serif;color:#b91c1c;padding:24px">Предпросмотр не загрузился. Проверьте связь и попробуйте изменить текст ещё раз.</p>';
            }
        }).then(function () {
            if (request === previewRequest) {
                frame.classList.remove('is-loading');
            }
        });
    }

    function fitFrame() {
        try {
            var doc = iframe.contentDocument;
            if (doc && doc.documentElement) {
                iframe.style.height = Math.max(240, Math.min(doc.documentElement.scrollHeight + 4, 1400)) + 'px';
            }
        } catch (e) { /* высота останется прежней */ }
    }
    iframe.addEventListener('load', fitFrame);

    root.addEventListener('click', function (event) {
        var device = event.target.closest('[data-et-device]');
        if (!device) {
            return;
        }
        Array.prototype.forEach.call(root.querySelectorAll('[data-et-device]'), function (button) {
            button.classList.toggle('is-active', button === device);
        });
        frame.classList.toggle('is-phone', device.getAttribute('data-et-device') === 'phone');
        setTimeout(fitFrame, 50);
    });

    /* ── Изменения ───────────────────────────────────────── */
    function changed() {
        if (!dirty) {
            dirty = true;
            dirtyBox.textContent = 'Есть несохранённые изменения';
            dirtyBox.classList.add('is-dirty');
        }
        updateCounter();
        syncVariables();
        schedulePreview();
    }

    form.addEventListener('input', function (event) {
        if (!event.target.closest('[data-et-test-email]')) {
            changed();
        }
    });
    form.addEventListener('change', function (event) {
        if (event.target.matches('[data-et-active]')) {
            root.querySelector('[data-et-active-text]').textContent = event.target.checked ? 'Письмо отправляется' : 'Выключено';
        }
        if (!event.target.closest('[data-et-test-email]')) {
            changed();
        }
    });
    form.addEventListener('submit', function () {
        if (editor) {
            editor.syncTextarea();
        }
        submitting = true;
    });
    window.addEventListener('beforeunload', function (event) {
        if (dirty && !submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    subject.addEventListener('focus', function () { lastTarget = 'subject'; });

    root.addEventListener('click', function (event) {
        var insert = event.target.closest('[data-et-insert]');
        if (insert) {
            insertVariable(insert.getAttribute('data-et-insert'));
            return;
        }
        var remove = event.target.closest('[data-et-remove]');
        if (remove) {
            remove.closest('[data-et-var]').remove();
            changed();
            return;
        }
        if (event.target.closest('[data-et-slug-unlock]')) {
            var slug = root.querySelector('[data-et-slug]');
            slug.readOnly = false;
            slug.focus();
            event.target.closest('[data-et-slug-unlock]').hidden = true;
            root.querySelector('[data-et-slug-hint]').classList.add('is-warn');
            return;
        }
        if (event.target.closest('[data-et-reset]') && config.defaults) {
            subject.value = config.defaults.subject;
            if (editor) {
                editor.html(config.defaults.body);
                editor.syncTextarea();
            } else {
                body.value = config.defaults.body;
            }
            changed();
            dirtyBox.textContent = 'Подставлен исходный текст — нажмите «Сохранить», чтобы оставить его';
        }
    });

    /* ── Тестовое письмо ─────────────────────────────────── */
    function showResult(text, ok) {
        testResult.hidden = false;
        testResult.textContent = text;
        testResult.className = 'et-result ' + (ok ? 'is-ok' : 'is-error');
    }

    function sendTest() {
        var email = testEmail.value.trim();
        if (!email) {
            showResult('Укажите адрес, на который отправить письмо.', false);
            testEmail.focus();
            return;
        }
        var data = contentData();
        data.append('email', email);
        testButton.disabled = true;
        testButton.textContent = 'Отправляем…';

        fetch(config.testUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            return response.json();
        }).then(function (result) {
            showResult(result.message, !!result.success);
        }).catch(function () {
            showResult('Сервер не ответил. Проверьте связь и попробуйте ещё раз.', false);
        }).then(function () {
            testButton.disabled = false;
            testButton.textContent = 'Отправить';
        });
    }
    testButton.addEventListener('click', sendTest);
    testEmail.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendTest();
        }
    });

    /* ── Запуск ──────────────────────────────────────────── */
    if ($ && $.fn && $.fn.trumbowyg) {
        var $body = $(body);
        $body.trumbowyg({
            lang: 'ru',
            autogrow: true,
            removeformatPasted: true,
            btns: [
                ['viewHTML'],
                ['undo', 'redo'],
                ['formatting'],
                ['strong', 'em', 'del'],
                ['link'],
                ['insertImage'],
                ['justifyLeft', 'justifyCenter', 'justifyRight'],
                ['unorderedList', 'orderedList'],
                ['table'],
                ['horizontalRule'],
                ['removeformat'],
                ['fullscreen']
            ]
        });
        editor = $body.data('trumbowyg') || null;
        $body.on('tbwchange tbwpaste', changed);
        $body.on('tbwfocus', function () { lastTarget = 'body'; });
        $body.on('tbwblur', function () {
            if (editor) {
                editor.saveRange();
            }
        });
    }
    body.addEventListener('focus', function () { lastTarget = 'body'; });

    updateCounter();
    syncVariables();
    loadPreview();
})(window.jQuery);
