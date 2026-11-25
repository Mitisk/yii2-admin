/**
 * Журналы (/admin/log/): вкладки и «Ошибки сайта» — лог приложения, собранный в проблемы.
 * Журнал действий рисует сервер; здесь для него только запоминание вкладки.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-lg]');
    if (!root) {
        return;
    }

    var STORE = 'admin.log.';
    function remember(key, value) {
        try { localStorage.setItem(STORE + key, value); } catch (e) { /* хранилище недоступно */ }
    }
    function recall(key) {
        try { return localStorage.getItem(STORE + key); } catch (e) { return null; }
    }

    /* ── Вкладки ─────────────────────────────────────────── */
    var tabs = root.querySelectorAll('[data-lg-tab]');
    var panes = root.querySelectorAll('[data-lg-pane]');
    var systemPane = root.querySelector('[data-lg-pane="system"]');
    var systemLoaded = false;

    function show(name, updateHash) {
        Array.prototype.forEach.call(tabs, function (tab) {
            var on = tab.getAttribute('data-lg-tab') === name;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(panes, function (pane) {
            pane.hidden = pane.getAttribute('data-lg-pane') !== name;
        });
        if (updateHash) {
            history.replaceState(null, '', name === 'system' ? '#system' : location.pathname + location.search);
        }
        if (name === 'system' && !systemLoaded) {
            systemLoaded = true;
            load(true);
        }
    }

    Array.prototype.forEach.call(tabs, function (tab) {
        tab.addEventListener('click', function () {
            show(tab.getAttribute('data-lg-tab'), true);
        });
    });

    if (!systemPane) {
        return;
    }

    /* ── Ошибки сайта ────────────────────────────────────── */
    var url = systemPane.getAttribute('data-url');
    var list = systemPane.querySelector('[data-lg-list]');
    var summaryBox = systemPane.querySelector('[data-lg-summary]');
    var countBox = systemPane.querySelector('[data-lg-count]');
    var moreButton = systemPane.querySelector('[data-lg-more]');
    var levelSelect = systemPane.querySelector('[data-lg-level]');
    var searchInput = systemPane.querySelector('[data-lg-search]');
    var noiseBox = systemPane.querySelector('[data-lg-noise]');
    var refreshButton = systemPane.querySelector('[data-lg-refresh]');
    var viewButtons = systemPane.querySelectorAll('[data-lg-view]');
    var badge = root.querySelector('[data-lg-badge]');

    var state = {
        view: recall('view') === 'list' ? 'list' : 'groups',
        offset: 0,
        request: 0
    };
    noiseBox.checked = recall('noise') === '1';

    var LEVELS = {
        error: 'Ошибка',
        warning: 'Предупреждение',
        info: 'Сообщение',
        trace: 'Трассировка',
        profile: 'Профиль',
        'profile begin': 'Профиль',
        'profile end': 'Профиль'
    };

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function plural(n, one, few, many) {
        var mod10 = n % 10;
        var mod100 = n % 100;
        if (mod10 === 1 && mod100 !== 11) {
            return n + ' ' + one;
        }
        return n + ' ' + (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? few : many);
    }

    function setView(view) {
        state.view = view;
        Array.prototype.forEach.call(viewButtons, function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-lg-view') === view);
        });
    }

    function tone(item) {
        if (item.noise) {
            return 'noise';
        }
        return item.level === 'error' ? 'error' : (item.level === 'warning' ? 'warning' : 'info');
    }

    function who(row) {
        if (!row.user) {
            return '<span class="lg-muted">гость</span>';
        }
        return esc(row.userName ? row.userName + ' (#' + row.user + ')' : '#' + row.user);
    }

    function address(row) {
        if (!row.url) {
            return '<span class="lg-muted">—</span>';
        }
        return (row.method ? '<span class="lg-method">' + esc(row.method) + '</span> ' : '') + esc(row.url);
    }

    function head(item) {
        var sub = [];
        if (item['class']) {
            sub.push(esc(item['class']));
        }
        if (item.where) {
            sub.push(esc(item.where));
        }
        if (!item['class'] && item.category && item.category !== 'application') {
            sub.push(esc(item.category));
        }
        return '<span class="lg-prob__title">' + esc(item.title)
            + (item.code ? ' <span class="lg-code">' + esc(item.code) + '</span>' : '') + '</span>'
            + (sub.length ? '<span class="lg-prob__sub">' + sub.join(' · ') + '</span>' : '');
    }

    function stack(item) {
        if (!item.full) {
            return '';
        }
        return '<div class="lg-stack"><button type="button" class="lg-btn lg-btn--small" data-lg-copy>Скопировать</button>'
            + '<pre>' + esc(item.title) + '\n' + esc(item.full) + '</pre></div>';
    }

    function groupRow(item) {
        var cases = item.cases.map(function (row) {
            return '<tr><td>' + esc(row.when) + '</td><td>' + who(row) + '</td><td>' + esc(row.ip || '—')
                + '</td><td class="lg-url">' + address(row) + '</td></tr>';
        }).join('');
        var when = '<span class="lg-prob__when">' + esc(item.last)
            + (item.count > 1 ? '<small>первая ' + esc(item.first) + '</small>' : '') + '</span>';

        return '<details class="lg-prob lg-prob--' + tone(item) + '"><summary>'
            + '<span class="lg-count-badge" title="Сколько раз повторилась">×' + esc(item.count) + '</span>'
            + '<span class="lg-prob__text">' + head(item) + '</span>' + when
            + '<i class="icon-chevron-down" aria-hidden="true"></i></summary>'
            + '<div class="lg-body">'
            + '<p class="lg-note">' + esc(LEVELS[item.level] || item.level)
            + (item.today ? ' · сегодня ' + plural(item.today, 'раз', 'раза', 'раз') : ' · сегодня не повторялась')
            + (item.category ? ' · категория ' + esc(item.category) : '') + '</p>'
            + '<table class="lg-cases"><caption>Последние случаи</caption><thead><tr><th>Когда</th><th>Пользователь</th><th>IP</th><th>Страница</th></tr></thead><tbody>'
            + cases + '</tbody></table>'
            + stack(item) + '</div></details>';
    }

    function listRow(item) {
        return '<details class="lg-prob lg-prob--' + tone(item) + '"><summary>'
            + '<span class="lg-time">' + esc(item.when) + '</span>'
            + '<span class="lg-prob__text">' + head(item) + '</span>'
            + '<i class="icon-chevron-down" aria-hidden="true"></i></summary>'
            + '<div class="lg-body"><dl class="lg-meta">'
            + '<div><dt>Уровень</dt><dd>' + esc(LEVELS[item.level] || item.level) + '</dd></div>'
            + '<div><dt>Пользователь</dt><dd>' + who(item) + '</dd></div>'
            + '<div><dt>IP</dt><dd>' + esc(item.ip || '—') + '</dd></div>'
            + (item.url ? '<div><dt>Страница</dt><dd class="lg-url">' + address(item) + '</dd></div>' : '')
            + (item.category ? '<div><dt>Категория</dt><dd>' + esc(item.category) + '</dd></div>' : '')
            + '</dl>' + stack(item) + '</div></details>';
    }

    function renderSummary(summary) {
        if (summary.empty) {
            summaryBox.innerHTML = '';
            return;
        }
        var errors = Number(summary.errorsToday) || 0;
        var warnings = Number(summary.warningsToday) || 0;
        var cards = [
            '<div class="lg-stat lg-stat--' + (errors ? 'error' : 'ok') + '"><span>Ошибки сайта сегодня</span><b>'
                + (errors || 'нет') + '</b></div>',
            '<div class="lg-stat' + (warnings ? ' lg-stat--warning' : '') + '"><span>Предупреждения сегодня</span><b>'
                + (warnings || 'нет') + '</b></div>',
            '<div class="lg-stat"><span>Ошибки посетителей в логе (404, 403…)</span><b>' + (Number(summary.noise) || 0) + '</b></div>',
            '<div class="lg-stat"><span>Лог на диске' + (summary.from ? ', показан с ' + esc(summary.from) : '')
                + '</span><b>' + esc(summary.size) + '</b></div>'
        ];
        summaryBox.innerHTML = cards.join('')
            + (summary.truncated
                ? '<p class="lg-note">Лог большой: показаны последние записи. Более старые остаются в файлах на сервере.</p>'
                : '');

        if (badge) {
            badge.hidden = !errors;
            badge.textContent = errors;
            badge.title = 'Ошибок сайта сегодня';
        }
    }

    function emptyState(summary, filtered) {
        if (summary.empty) {
            return '<div class="lg-card lg-none"><div class="lg-none__title">Лог пуст</div>'
                + '<p>Ошибок и предупреждений сайт не записывал.</p></div>';
        }
        return '<div class="lg-card lg-none"><div class="lg-none__title">'
            + (filtered ? 'Ничего не найдено' : 'Записей нет') + '</div>'
            + (filtered ? '<p>Под эти условия не подходит ни одна запись лога.</p>' : '') + '</div>';
    }

    function load(reset) {
        if (reset) {
            state.offset = 0;
        }
        var query = [
            'view=' + state.view,
            'level=' + encodeURIComponent(levelSelect.value),
            'search=' + encodeURIComponent(searchInput.value.trim()),
            'hide_noise=' + (noiseBox.checked ? '1' : '0'),
            'offset=' + state.offset
        ].join('&');
        var request = ++state.request;

        systemPane.classList.add('is-loading');
        if (reset && !list.children.length) {
            list.innerHTML = '<div class="lg-card lg-none">Читаем лог…</div>';
        }

        fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + query, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(response.status === 403 ? 'Лог приложения доступен администратору.' : 'Сервер ответил ошибкой ' + response.status + '.');
            }
            return response.json();
        }).then(function (data) {
            if (request !== state.request) {
                return;
            }
            var filtered = levelSelect.value !== '' || searchInput.value.trim() !== '' || noiseBox.checked;
            var html = data.items.map(state.view === 'groups' ? groupRow : listRow).join('');

            renderSummary(data.summary);
            if (reset) {
                list.innerHTML = html || emptyState(data.summary, filtered);
            } else {
                list.insertAdjacentHTML('beforeend', html);
            }
            state.offset += data.items.length;

            if (!data.items.length && reset) {
                countBox.textContent = '';
            } else if (state.view === 'groups') {
                countBox.textContent = plural(data.total, 'проблема', 'проблемы', 'проблем') + ' — '
                    + plural(data.matched, 'запись', 'записи', 'записей') + ' лога';
            } else {
                countBox.textContent = 'Показано ' + state.offset + ' из ' + data.total;
            }
            moreButton.hidden = !data.hasMore;
        }).catch(function (error) {
            if (request !== state.request) {
                return;
            }
            list.innerHTML = '<div class="lg-card lg-none"><div class="lg-none__title">Лог не прочитан</div><p>'
                + esc(error.message || 'Нет связи с сервером.') + '</p></div>';
            moreButton.hidden = true;
        }).then(function () {
            if (request === state.request) {
                systemPane.classList.remove('is-loading');
            }
        });
    }

    Array.prototype.forEach.call(viewButtons, function (button) {
        button.addEventListener('click', function () {
            setView(button.getAttribute('data-lg-view'));
            remember('view', state.view);
            list.innerHTML = '';
            load(true);
        });
    });

    levelSelect.addEventListener('change', function () { load(true); });
    noiseBox.addEventListener('change', function () {
        remember('noise', noiseBox.checked ? '1' : '0');
        load(true);
    });
    refreshButton.addEventListener('click', function () { load(true); });
    moreButton.addEventListener('click', function () { load(false); });

    var searchTimer = null;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { load(true); }, 350);
    });
    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            clearTimeout(searchTimer);
            load(true);
        }
    });

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-lg-copy]');
        if (!button) {
            return;
        }
        var text = button.parentNode.querySelector('pre').textContent;
        var done = function () {
            button.textContent = 'Скопировано';
            setTimeout(function () { button.textContent = 'Скопировать'; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
            done();
        }
    });

    setView(state.view);
    if (location.hash === '#system') {
        show('system', false);
    }
})();
