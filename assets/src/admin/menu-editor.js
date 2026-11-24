/*
 * Редактор меню (/admin/menu/): дерево пунктов с перетаскиванием, форма пункта, несколько меню.
 * Исходник; в пакет попадает собранный assets/js/menu-editor.min.js (sh assets/src/build.sh).
 *
 * Все меню лежат в памяти страницы (state.menus) и уходят на сервер одним запросом: переключение
 * между меню ничего не теряет. Разметка строится через DOM, текст — только textContent.
 */
(function () {
    'use strict';

    var root = document.getElementById('me-editor');
    var dataNode = document.getElementById('me-data');
    if (!root || !dataNode) {
        return;
    }

    var state = {
        menus: (JSON.parse(dataNode.textContent || '{}').menus || []).map(function (menu) {
            menu.items = menu.items || [];
            return menu;
        }),
        active: 0,
        // Что открыто в форме: {item, list, isNew} или null
        editing: null,
        dirty: false,
        saving: false,
        removed: null
    };

    var $ = function (id) {
        return document.getElementById(id);
    };
    var el = {
        tabs: $('me-tabs'), list: $('me-list'), empty: $('me-empty'), title: $('me-menu-title'),
        lead: $('me-menu-lead'), settings: $('me-settings'), name: $('me-menu-name'), alias: $('me-menu-alias'),
        aliasHint: $('me-menu-alias-hint'), menuDelete: $('me-menu-delete'), menuError: $('me-menu-error'),
        addRoot: $('me-add-root'), form: $('me-form'), formEmpty: $('me-form-empty'), formTitle: $('me-form-title'),
        text: $('me-text'), textError: $('me-text-error'), href: $('me-href'), icon: $('me-icon'),
        iconField: $('me-icon-field'), iconPreview: $('me-icon-preview'), iconClear: $('me-icon-clear'),
        icons: $('me-icons'), rule: $('me-rule'), blank: $('me-blank'), hint: $('me-title'),
        apply: $('me-form-apply'), cancel: $('me-form-cancel'), save: $('me-save'), stateText: $('me-state'),
        deleteForm: $('me-delete-form')
    };

    function menu() {
        return state.menus[state.active];
    }

    function create(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function iconNode(className) {
        var i = document.createElement('i');
        // Класс иконки — только буквы, цифры, пробел, дефис и подчёркивание
        i.className = /^[a-z0-9 _-]+$/i.test(className || '') ? className : '';
        return i;
    }

    function setDirty(value) {
        state.dirty = value;
        el.stateText.textContent = value ? 'Есть несохранённые изменения' : '';
        el.stateText.classList.toggle('is-dirty', value);
    }

    // --- Вкладки меню ---

    function renderTabs() {
        el.tabs.textContent = '';
        state.menus.forEach(function (item, index) {
            var tab = create('button', 'me-tab' + (index === state.active ? ' is-active' : ''), item.name || 'Новое меню');
            tab.type = 'button';
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', index === state.active ? 'true' : 'false');
            tab.addEventListener('click', function () {
                switchMenu(index);
            });
            el.tabs.appendChild(tab);
        });
        var add = create('button', 'me-tab me-tab--add', '+ Меню');
        add.type = 'button';
        add.title = 'Создать ещё одно меню — например, для подвала сайта';
        add.addEventListener('click', function () {
            state.menus.push({ id: null, alias: '', name: '', locked: false, items: [] });
            setDirty(true);
            switchMenu(state.menus.length - 1);
            el.name.focus();
        });
        el.tabs.appendChild(add);
    }

    function switchMenu(index) {
        closeForm();
        state.active = index;
        renderAll();
    }

    function renderMenuHead() {
        var current = menu();
        el.title.textContent = current.name || 'Новое меню';
        el.lead.textContent = current.alias === 'admin'
            ? 'Боковое меню админки. Изменения появятся слева после сохранения.'
            : 'Своё меню проекта: в коде сайта его получают по алиасу.';
        el.settings.hidden = !!current.locked;
        el.name.value = current.name || '';
        el.alias.value = current.alias || '';
        el.alias.readOnly = current.id !== null;
        el.aliasHint.hidden = current.id !== null;
        el.menuDelete.hidden = current.id === null && state.menus.length < 2;
        el.menuDelete.textContent = 'Удалить меню';
        el.menuDelete.classList.remove('is-armed');
        showMenuError(current.error || '');
    }

    function showMenuError(message) {
        el.menuError.textContent = message;
        el.menuError.hidden = !message;
    }

    // --- Дерево пунктов ---

    function ruleLabel(value) {
        if (!value) {
            return '';
        }
        var option = Array.prototype.filter.call(el.rule.options, function (item) {
            return item.value === value;
        })[0];
        return option ? option.textContent.trim() : value;
    }

    function renderRow(item, list, level) {
        var li = create('li', 'me-item');
        li._item = item;

        var row = create('div', 'me-row' + (state.editing && state.editing.item === item ? ' is-editing' : ''));
        var handle = create('span', 'me-row__handle');
        handle.title = 'Перетащить';
        handle.appendChild(iconNode('icon-menu'));
        row.appendChild(handle);

        var icon = create('span', 'me-row__icon');
        if (item.icon && item.icon !== 'empty') {
            icon.appendChild(iconNode(item.icon));
        }
        row.appendChild(icon);

        var body = create('button', 'me-row__body');
        body.type = 'button';
        body.title = 'Изменить пункт';
        body.appendChild(create('span', 'me-row__text', item.text));
        body.appendChild(create('span', 'me-row__href', item.href === '#' ? 'группа' : item.href));
        body.addEventListener('click', function () {
            openForm(item, list, false);
        });
        row.appendChild(body);

        if (item.rule) {
            var chip = create('span', 'me-row__rule', ruleLabel(item.rule));
            chip.title = 'Кто видит: ' + ruleLabel(item.rule);
            row.appendChild(chip);
        }
        if (item.target === '_blank') {
            var blank = create('span', 'me-row__flag');
            blank.title = 'Открывается в новой вкладке';
            blank.appendChild(iconNode('icon-external-link'));
            row.appendChild(blank);
        }

        var tools = create('span', 'me-row__tools');
        if (level === 1) {
            var addChild = create('button', 'me-tool');
            addChild.type = 'button';
            addChild.title = 'Добавить подпункт';
            addChild.appendChild(iconNode('icon-plus'));
            addChild.addEventListener('click', function () {
                item.children = item.children || [];
                openForm(blankItem(true), item.children, true);
            });
            tools.appendChild(addChild);
        }
        var remove = create('button', 'me-tool me-tool--danger');
        remove.type = 'button';
        remove.title = 'Удалить пункт';
        remove.appendChild(iconNode('icon-trash-2'));
        remove.addEventListener('click', function () {
            removeItem(item, list);
        });
        tools.appendChild(remove);
        row.appendChild(tools);
        li.appendChild(row);

        if (level === 1) {
            var children = create('ul', 'me-list me-list--children');
            children._items = item.children = item.children || [];
            item.children.forEach(function (child) {
                children.appendChild(renderRow(child, item.children, 2));
            });
            li.appendChild(children);
        }
        return li;
    }

    var sortables = [];

    function renderList() {
        sortables.forEach(function (instance) {
            instance.destroy();
        });
        sortables = [];

        var current = menu();
        el.list.textContent = '';
        el.list._items = current.items;
        current.items.forEach(function (item) {
            el.list.appendChild(renderRow(item, current.items, 1));
        });
        el.empty.hidden = current.items.length > 0;

        if (typeof Sortable === 'undefined') {
            return;
        }
        [el.list].concat(Array.prototype.slice.call(el.list.querySelectorAll('.me-list--children'))).forEach(function (list) {
            sortables.push(new Sortable(list, {
                group: 'me-menu',
                handle: '.me-row__handle',
                animation: 150,
                ghostClass: 'me-item--ghost',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onMove: function (event) {
                    // Пункт с подпунктами нельзя вложить: уровней всего два
                    var hasChildren = event.dragged._item && event.dragged._item.children && event.dragged._item.children.length;
                    return !(hasChildren && event.to.classList.contains('me-list--children'));
                },
                onStart: function () {
                    el.list.classList.add('is-dragging');
                },
                onEnd: function () {
                    el.list.classList.remove('is-dragging');
                    syncFromDom();
                }
            }));
        });
    }

    /** После перетаскивания порядок и вложенность берутся из разметки. */
    function syncFromDom() {
        var read = function (list, level) {
            return Array.prototype.map.call(list.children, function (li) {
                var item = li._item;
                var childList = li.querySelector(':scope > .me-list--children');
                if (level === 1) {
                    item.children = childList ? read(childList, 2) : [];
                } else {
                    delete item.children;
                }
                return item;
            });
        };
        menu().items = read(el.list, 1);
        setDirty(true);
        closeForm();
        renderList();
    }

    function blankItem(isChild) {
        return { text: '', href: '', icon: isChild ? 'empty' : '', target: '_self', rule: '', title: '' };
    }

    function removeItem(item, list) {
        var index = list.indexOf(item);
        if (index === -1) {
            return;
        }
        list.splice(index, 1);
        state.removed = { item: item, list: list, index: index };
        if (state.editing && state.editing.item === item) {
            closeForm();
        }
        setDirty(true);
        renderList();
        el.stateText.textContent = '';
        var undo = create('button', 'me-link', 'Вернуть «' + item.text + '»');
        undo.type = 'button';
        undo.addEventListener('click', function () {
            var removed = state.removed;
            if (removed) {
                removed.list.splice(Math.min(removed.index, removed.list.length), 0, removed.item);
                state.removed = null;
                setDirty(true);
                renderList();
            }
        });
        el.stateText.appendChild(document.createTextNode('Пункт удалён. '));
        el.stateText.appendChild(undo);
    }

    // --- Форма пункта ---

    function openForm(item, list, isNew) {
        state.editing = { item: item, list: list, isNew: isNew };
        var isChild = list !== menu().items;
        el.formEmpty.hidden = true;
        el.form.hidden = false;
        el.formTitle.textContent = isNew ? (isChild ? 'Новый подпункт' : 'Новый пункт') : 'Пункт «' + item.text + '»';
        el.apply.textContent = isNew ? 'Добавить' : 'Готово';
        el.text.value = item.text || '';
        el.href.value = item.href === '#' ? '' : (item.href || '');
        el.icon.value = item.icon === 'empty' ? '' : (item.icon || '');
        // У подпунктов в боковом меню иконок нет
        el.iconField.hidden = isChild;
        el.blank.checked = item.target === '_blank';
        el.hint.value = item.title || '';
        el.textError.hidden = true;

        // Значение, которого нет в списке (право удалено или задано кодом), не теряется
        var known = Array.prototype.some.call(el.rule.options, function (option) {
            return option.value === (item.rule || '');
        });
        var extra = el.rule.querySelector('option[data-extra]');
        if (extra) {
            extra.remove();
        }
        if (!known) {
            var option = create('option', '', item.rule);
            option.value = item.rule;
            option.setAttribute('data-extra', '1');
            el.rule.insertBefore(option, el.rule.options[1] || null);
        }
        el.rule.value = item.rule || '';

        refreshIconPreview();
        if (!isNew) {
            renderList();
        }
        el.text.focus();
    }

    function closeForm() {
        state.editing = null;
        el.form.hidden = true;
        el.formEmpty.hidden = false;
    }

    function refreshIconPreview() {
        el.iconPreview.textContent = '';
        var value = el.icon.value.trim();
        if (value) {
            el.iconPreview.appendChild(iconNode(value));
        }
        Array.prototype.forEach.call(el.icons.children, function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-icon') === value);
        });
    }

    el.form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!state.editing) {
            return;
        }
        var text = el.text.value.trim();
        if (!text) {
            el.textError.hidden = false;
            el.text.focus();
            return;
        }
        var editing = state.editing;
        var item = editing.item;
        var isChild = editing.list !== menu().items;
        item.text = text;
        item.href = el.href.value.trim() || '#';
        item.icon = isChild ? 'empty' : el.icon.value.trim();
        item.rule = el.rule.value;
        item.target = el.blank.checked ? '_blank' : '_self';
        item.title = el.hint.value.trim();
        if (editing.isNew) {
            editing.list.push(item);
        }
        setDirty(true);
        closeForm();
        renderList();
    });

    el.cancel.addEventListener('click', function () {
        closeForm();
        renderList();
    });
    el.icon.addEventListener('input', refreshIconPreview);
    el.iconClear.addEventListener('click', function () {
        el.icon.value = '';
        refreshIconPreview();
    });
    el.icons.addEventListener('click', function (event) {
        var button = event.target.closest('.me-icons__item');
        if (button) {
            el.icon.value = button.getAttribute('data-icon');
            refreshIconPreview();
        }
    });
    el.text.addEventListener('input', function () {
        el.textError.hidden = true;
    });
    el.addRoot.addEventListener('click', function () {
        openForm(blankItem(false), menu().items, true);
    });

    // --- Настройки меню ---

    el.name.addEventListener('input', function () {
        menu().name = el.name.value;
        el.title.textContent = el.name.value || 'Новое меню';
        setDirty(true);
        renderTabs();
    });
    el.alias.addEventListener('input', function () {
        if (menu().id === null) {
            menu().alias = el.alias.value.trim();
            setDirty(true);
        }
    });

    var armTimer = null;
    el.menuDelete.addEventListener('click', function () {
        var current = menu();
        if (current.id === null) {
            // Ещё не сохранено — просто убираем вкладку
            state.menus.splice(state.active, 1);
            switchMenu(Math.max(0, state.active - 1));
            return;
        }
        // Два нажатия вместо confirm(): первое «взводит» кнопку на несколько секунд
        if (!el.menuDelete.classList.contains('is-armed')) {
            el.menuDelete.classList.add('is-armed');
            el.menuDelete.textContent = 'Удалить «' + current.name + '» без возврата?';
            clearTimeout(armTimer);
            armTimer = setTimeout(function () {
                el.menuDelete.classList.remove('is-armed');
                el.menuDelete.textContent = 'Удалить меню';
            }, 4000);
            return;
        }
        state.dirty = false;
        el.deleteForm.action = root.getAttribute('data-delete-url') + (root.getAttribute('data-delete-url').indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent(current.id);
        el.deleteForm.submit();
    });

    // --- Сохранение ---

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function strip(items) {
        return items.map(function (item) {
            var copy = {
                text: item.text, href: item.href, icon: item.icon, target: item.target, rule: item.rule, title: item.title
            };
            if (item.children && item.children.length) {
                copy.children = strip(item.children);
            }
            return copy;
        });
    }

    el.save.addEventListener('click', function () {
        if (state.saving) {
            return;
        }
        state.menus.forEach(function (item) {
            item.error = '';
        });
        var body = new FormData();
        body.append('menus', JSON.stringify(state.menus.map(function (item) {
            return { id: item.id, name: item.name, alias: item.alias, items: strip(item.items) };
        })));
        body.append('_csrf', csrf());

        state.saving = true;
        el.save.disabled = true;
        el.save.textContent = 'Сохраняем…';

        fetch(root.getAttribute('data-save-url'), {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    // Боковое меню рисует сервер — перезагрузка покажет его уже новым
                    state.dirty = false;
                    window.location.reload();
                    return;
                }
                var first = null;
                Object.keys(data.errors || {}).forEach(function (index) {
                    if (state.menus[index]) {
                        state.menus[index].error = data.errors[index];
                        first = first === null ? Number(index) : first;
                    }
                });
                if (first !== null && first !== state.active) {
                    state.active = first;
                    renderAll();
                } else {
                    showMenuError(menu().error || data.message || 'Меню не сохранено.');
                }
                el.stateText.textContent = data.message || 'Меню не сохранено.';
                el.stateText.classList.add('is-dirty');
            })
            .catch(function () {
                el.stateText.textContent = 'Меню не сохранено: нет связи с сервером.';
                el.stateText.classList.add('is-dirty');
            })
            .then(function () {
                state.saving = false;
                el.save.disabled = false;
                el.save.textContent = 'Сохранить';
            });
    });

    window.addEventListener('beforeunload', function (event) {
        if (state.dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    function renderAll() {
        renderTabs();
        renderMenuHead();
        renderList();
    }

    if (!state.menus.length) {
        state.menus.push({ id: null, alias: '', name: '', locked: false, items: [] });
    }
    renderAll();
})();
