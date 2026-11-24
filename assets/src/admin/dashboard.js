/*
 * Главная админки: виджеты верхнего ряда (порядок, скрыть, добавить из списка), очистка кэша,
 * выбор компонента для центрального блока, заметки.
 * Исходник; в пакет попадает собранный assets/js/page/index.min.js (sh assets/src/build.sh).
 * Зависит от jQuery, Bootstrap, SortableJS и Trumbowyg, подключённых IndexAsset.
 */
(function ($) {
    'use strict';

    var urls = (window.adminConfig && window.adminConfig.urls) || {};

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function post(url, data) {
        var body = new FormData();
        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });
        body.append('_csrf', csrf());
        return fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json();
        });
    }

    // --- Короткие сообщения в углу вместо alert() ---

    function toast(message, isError) {
        var box = document.getElementById('db-toasts');
        if (!box) {
            box = document.createElement('div');
            box.id = 'db-toasts';
            box.className = 'db-toasts';
            box.setAttribute('aria-live', 'polite');
            document.body.appendChild(box);
        }
        var item = document.createElement('div');
        item.className = 'db-toast' + (isError ? ' db-toast--error' : '');
        item.textContent = message;
        box.appendChild(item);
        setTimeout(function () {
            item.classList.add('is-leaving');
            setTimeout(function () {
                item.remove();
            }, 250);
        }, isError ? 5000 : 2500);
    }

    // --- Верхний ряд: порядок ---

    var topRow = document.querySelector('.dashboard-top-widget-section');
    var sortable = null;

    function initSortable() {
        if (!topRow || typeof Sortable === 'undefined') {
            return;
        }
        if (sortable) {
            sortable.destroy();
        }
        sortable = new Sortable(topRow, {
            draggable: '.dashboard-top-widget-item',
            handle: '.dashboard-top-widget-item-move',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            filter: '.dashboard-add-top-widget-section',
            preventOnFilter: false,
            onEnd: function (event) {
                if (event.oldIndex !== event.newIndex) {
                    saveOrder();
                }
            }
        });
    }

    function saveOrder() {
        var aliases = Array.prototype.map.call(
            topRow.querySelectorAll('.dashboard-top-widget-item'),
            function (item) {
                return item.dataset.name;
            }
        ).filter(Boolean);
        if (!aliases.length) {
            return;
        }
        post(urls.updateWidgetOrder, { aliases: JSON.stringify(aliases) })
            .then(function (data) {
                if (!data.success) {
                    toast('Порядок не сохранился: ' + (data.message || 'ошибка'), true);
                }
            })
            .catch(function () {
                toast('Порядок не сохранился: нет связи с сервером', true);
            });
    }

    // --- Верхний ряд: добавить и скрыть ---

    var modalEl = document.getElementById('addWidgetModal');
    var pickList = document.getElementById('widgetPickList');
    var pickEmpty = document.getElementById('widgetPickEmpty');
    var modalError = document.getElementById('widgetError');

    function showModalError(message) {
        if (modalError) {
            modalError.textContent = message || '';
            modalError.hidden = !message;
        }
    }

    function refreshPickEmpty() {
        if (pickEmpty && pickList) {
            pickEmpty.hidden = pickList.children.length > 0;
        }
    }

    /** Строка списка «Добавить виджет» для только что скрытого виджета. */
    function offerWidget(className, alias, title) {
        if (!pickList || !className || pickList.querySelector('[data-widget-alias="' + alias + '"]')) {
            return;
        }
        var row = document.createElement('div');
        row.className = 'db-pick__item';
        row.setAttribute('data-widget-class', className);
        row.setAttribute('data-widget-alias', alias);

        var body = document.createElement('div');
        body.className = 'db-pick__body';
        var titleEl = document.createElement('div');
        titleEl.className = 'db-pick__title';
        titleEl.textContent = title || alias;
        var code = document.createElement('div');
        code.className = 'db-pick__code';
        code.textContent = alias;
        body.appendChild(titleEl);
        body.appendChild(code);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'tf-button style-1 db-pick__add';
        button.textContent = 'Добавить';

        row.appendChild(body);
        row.appendChild(button);
        pickList.appendChild(row);
        refreshPickEmpty();
    }

    function addWidget(className, button, row) {
        if (!className || button.disabled) {
            return;
        }
        var label = button.textContent;
        button.disabled = true;
        button.textContent = 'Добавляем…';
        showModalError('');

        post(urls.addWidget, { class: className })
            .then(function (data) {
                if (!data.success) {
                    showModalError(data.message || 'Не удалось добавить виджет.');
                    return;
                }
                var holder = document.createElement('div');
                // Разметка приходит с нашего сервера — это отрисованный виджет
                holder.innerHTML = data.html;
                var addButton = topRow.querySelector('.dashboard-add-top-widget-section');
                while (holder.firstElementChild) {
                    topRow.insertBefore(holder.firstElementChild, addButton);
                }
                if (row) {
                    row.remove();
                    refreshPickEmpty();
                }
                initSortable();
                var modal = window.bootstrap && bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
                toast('Виджет добавлен');
            })
            .catch(function () {
                showModalError('Нет связи с сервером. Попробуйте ещё раз.');
            })
            .then(function () {
                button.disabled = false;
                button.textContent = label;
            });
    }

    function hideWidget(item) {
        var alias = item.dataset.name;
        post(urls.hideWidget, { alias: alias })
            .then(function (data) {
                if (!data.success) {
                    toast(data.message || 'Не удалось скрыть виджет', true);
                    return;
                }
                offerWidget(data.class, alias, data.title);
                item.classList.add('is-leaving');
                setTimeout(function () {
                    item.remove();
                    initSortable();
                }, 200);
                toast('Виджет скрыт. Вернуть — кнопкой «Добавить виджет»');
            })
            .catch(function () {
                toast('Не удалось скрыть виджет: нет связи с сервером', true);
            });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (target.closest('.dashboard-add-top-widget-section') && modalEl && window.bootstrap) {
            event.preventDefault();
            showModalError('');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }

        var pick = target.closest('.db-pick__add');
        if (pick) {
            var row = pick.closest('.db-pick__item');
            addWidget(row.getAttribute('data-widget-class'), pick, row);
            return;
        }

        var hide = target.closest('.dashboard-top-widget-item-hide');
        if (hide) {
            event.preventDefault();
            hideWidget(hide.closest('.dashboard-top-widget-item'));
        }
    });

    var classForm = document.getElementById('addWidgetForm');
    if (classForm) {
        classForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var input = document.getElementById('widgetClass');
            var className = input.value.trim();
            if (!className) {
                showModalError('Укажите класс виджета.');
                input.focus();
                return;
            }
            addWidget(className, document.getElementById('addWidgetBtn'), null);
        });
    }

    initSortable();

    // --- Очистка кэша ---

    document.addEventListener('click', function (event) {
        var button = event.target.closest('#btn-clear-cache');
        if (!button) {
            return;
        }
        event.preventDefault();
        if (button.classList.contains('is-busy')) {
            return;
        }
        var label = button.textContent;
        button.classList.add('is-busy');
        button.textContent = 'Очищаем…';
        post(button.getAttribute('href') || urls.clearCache, {})
            .then(function (data) {
                toast(data.success ? 'Кэш сайта очищен' : 'Кэш очистить не удалось', !data.success);
            })
            .catch(function () {
                toast('Кэш очистить не удалось: нет связи с сервером', true);
            })
            .then(function () {
                button.classList.remove('is-busy');
                button.textContent = label;
            });
    });

    // --- Центральный блок: выбрать компонент ---

    $(document).on('click', '.dashboard-add-center-widget-section', function (event) {
        event.preventDefault();
        var $block = $(this);

        $.get(urls.componentPopup, function (html) {
            var $modal = $(html);
            $('body').append($modal);
            var modal = new bootstrap.Modal($modal.get(0), { backdrop: true, keyboard: true });
            modal.show();

            $modal.on('click', '#component-picker-apply', function () {
                var alias = $modal.find('#component-select').val();
                if (!alias) {
                    toast('Выберите компонент', true);
                    return;
                }
                $.ajax({
                    url: urls.saveComponent,
                    method: 'POST',
                    data: { alias: alias, _csrf: csrf() },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        $block.replaceWith(response.html);
                        modal.hide();
                    } else {
                        toast(response.message || 'Компонент не сохранился', true);
                    }
                }).fail(function () {
                    toast('Компонент не сохранился: нет связи с сервером', true);
                });
            });

            $modal.on('click', '#component-picker-cancel, #component-picker-close', function () {
                modal.hide();
            });
            $modal.on('hidden.bs.modal', function () {
                $modal.remove();
            });
        });
    });

    // --- Заметки ---

    var $editor = $('#text-meta');
    if ($editor.length && $.fn.trumbowyg) {
        var $save = $('#btn-save');
        var $status = $('#note-status');
        var timer = null;
        var saving = false;
        var lastSent = '';

        $.ajaxSetup({ headers: { 'X-CSRF-Token': csrf() } });

        $editor.trumbowyg({
            lang: 'ru',
            imageWidthModalEdit: true,
            autogrow: true,
            btns: [
                ['strong', 'em', 'del'],
                ['unorderedList', 'orderedList'],
                ['link', 'insertImage'],
                ['table'],
                ['emoji'],
                ['formatting'],
                ['removeformat']
            ]
        });

        var html = function () {
            return $editor.trumbowyg('html');
        };

        var setStatus = function (text, dirty) {
            $status.text(text).toggleClass('is-dirty', !!dirty);
            $save.prop('hidden', !dirty);
        };

        var saveNote = function () {
            if (saving) {
                return;
            }
            var current = html();
            if (current === lastSent) {
                setStatus('Сохранено', false);
                return;
            }
            saving = true;
            $save.prop('disabled', true);
            setStatus('Сохраняем…', true);
            $.ajax({ url: urls.saveNote, method: 'POST', data: { text: current } })
                .done(function (response) {
                    if (response && response.ok) {
                        lastSent = current;
                        setStatus('Сохранено', false);
                    } else {
                        setStatus('Не сохранилось', true);
                    }
                })
                .fail(function () {
                    setStatus('Не сохранилось: нет связи', true);
                })
                .always(function () {
                    saving = false;
                    $save.prop('disabled', false);
                });
        };

        $editor.on('tbwchange tbwpaste', function () {
            if (html() === lastSent) {
                setStatus('Сохранено', false);
                return;
            }
            setStatus('Есть несохранённые изменения', true);
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(saveNote, 10000);
        });

        $save.on('click', function (event) {
            event.preventDefault();
            saveNote();
        });

        window.addEventListener('beforeunload', function () {
            var current = html();
            if (current !== lastSent && !saving && navigator.sendBeacon) {
                var body = new URLSearchParams({ text: current });
                body.append('_csrf', csrf());
                navigator.sendBeacon(urls.saveNote, body);
            }
        });

        $.get(urls.getNote).done(function (response) {
            if (response && response.ok) {
                $editor.trumbowyg('html', response.text || '');
                lastSent = String(response.text || '');
                setStatus('', false);
            }
        });
    }
})(jQuery);
