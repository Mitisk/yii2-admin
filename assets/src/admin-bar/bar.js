/**
 * Admin Bar — панель администратора на страницах сайта.
 * Ванильный JS, Shadow DOM, без зависимостей.
 *
 * Запуск:
 *  - server-режим: на странице есть <script type="application/json" id="admin-bar-state">;
 *  - client-режим: загрузчик вызывает window.AdminBar.boot(state, {css}).
 */
(function () {
    'use strict';

    if (window.AdminBar && window.AdminBar.booted) { return; }

    var PREFS_KEY = 'adminBar.prefs';

    /* ─── Иконки (Feather-подобные, stroke) ──────────────────── */
    var ICONS = {
        home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        list: '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        refresh: '<polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>',
        search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        menu: '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        chevron: '<polyline points="9 18 15 12 9 6"/>',
        user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        sun: '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        minus: '<line x1="5" y1="12" x2="19" y2="12"/>',
        update: '<circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/>',
        check: '<polyline points="20 6 9 17 4 12"/>',
        alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        external: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        settings: '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        layers: '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        command: '<path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3H6a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3V6a3 3 0 0 0-3-3 3 3 0 0 0-3 3 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/>',
        grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        trash: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        mail: '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        folder: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        tool: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        keyboard: '<rect x="2" y="6" width="20" height="12" rx="2"/><line x1="6" y1="10" x2="6" y2="10"/><line x1="10" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="14" y2="10"/><line x1="18" y1="10" x2="18" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/>'
    };

    function icon(name, size) {
        var body = ICONS[name] || ICONS.zap;
        var s = size ? ' style="width:' + size + 'px;height:' + size + 'px"' : '';
        return '<svg viewBox="0 0 24 24" aria-hidden="true"' + s + '>' + body + '</svg>';
    }

    /* ─── Утилиты ─────────────────────────────────────────────── */
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function el(html) {
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstChild;
    }
    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).slice(0, 2);
        return parts.map(function (p) { return p.charAt(0).toUpperCase(); }).join('') || 'A';
    }
    function loadPrefs() {
        try { return JSON.parse(localStorage.getItem(PREFS_KEY) || '{}') || {}; } catch (e) { return {}; }
    }
    function savePrefs(p) {
        try { localStorage.setItem(PREFS_KEY, JSON.stringify(p)); } catch (e) { /* приватный режим */ }
    }
    function matchHotkey(e, combo) {
        var parts = String(combo || '').toLowerCase().split('+');
        var key = parts.pop();
        if (e.key.toLowerCase() !== key && e.code.toLowerCase() !== 'key' + key) { return false; }
        return parts.indexOf('alt') > -1 === e.altKey
            && parts.indexOf('shift') > -1 === e.shiftKey
            && (parts.indexOf('ctrl') > -1 || parts.indexOf('cmd') > -1) === (e.ctrlKey || e.metaKey);
    }

    /* ─── Приложение ─────────────────────────────────────────── */
    function AdminBar(state, assets) {
        this.state = state || {};
        this.assets = assets || {};
        this.prefs = loadPrefs();
        this.editMode = false;
        this.openPop = null;
    }

    AdminBar.prototype.mount = function () {
        var self = this;
        this.host = document.querySelector('admin-bar') || document.createElement('admin-bar');
        if (!this.host.parentNode) { document.body.appendChild(this.host); }
        this.host.removeAttribute('hidden');
        this.shadow = this.host.attachShadow({ mode: 'open' });

        if (this.assets.css) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = this.assets.css;
            this.shadow.appendChild(link);
        }

        var prefs = this.state.prefs || {};
        this.root = el('<div class="ab-root"></div>');
        this.root.dataset.theme = this.prefs.theme || prefs.theme || 'dark';
        this.root.dataset.pos = this.prefs.position || prefs.position || 'bottom';
        if (this.prefs.collapsed) { this.root.classList.add('is-collapsed'); }
        this.shadow.appendChild(this.root);

        this.renderImpersonation();
        this.renderBar();
        this.renderFab();
        this.toasts = el('<div class="ab-toasts"></div>');
        this.root.appendChild(this.toasts);
        this.renderOverlay();
        this.bindGlobal();

        window.AdminBar.booted = true;
        document.dispatchEvent(new CustomEvent('admin-bar:ready', { detail: { bar: self } }));
    };

    /* ─── Рендер панели ──────────────────────────────────────── */
    AdminBar.prototype.renderBar = function () {
        var s = this.state, u = s.user || {}, urls = s.urls || {};
        var bar = el('<div class="ab-bar" role="toolbar" aria-label="Панель администратора"></div>');
        this.bar = bar;

        // Пользователь
        var avatar = u.avatar
            ? '<span class="ab-avatar"><img src="' + esc(u.avatar) + '" alt=""></span>'
            : '<span class="ab-avatar">' + esc(initials(u.name)) + '</span>';
        var userBtn = el('<button type="button" class="ab-btn ab-user" data-tip="Аккаунт">' + avatar + '<span class="ab-label">' + esc(u.name || 'Администратор') + '</span></button>');
        this.attachPop(userBtn, this.buildUserPop.bind(this));
        bar.appendChild(userBtn);

        bar.appendChild(el('<span class="ab-sep"></span>'));

        // Главная админки
        bar.appendChild(el('<a class="ab-btn is-icon" href="' + esc(urls.dashboard) + '" data-tip="Панель управления">' + icon('home') + '</a>'));

        // Меню
        var menuBtn = el('<button type="button" class="ab-btn is-icon" data-tip="Разделы">' + icon('grid') + '</button>');
        this.attachPop(menuBtn, this.buildMenuPop.bind(this));
        bar.appendChild(menuBtn);

        // Палитра
        var palBtn = el('<button type="button" class="ab-btn is-icon" data-tip="Поиск (Ctrl+K)">' + icon('search') + '</button>');
        palBtn.addEventListener('click', this.openPalette.bind(this));
        bar.appendChild(palBtn);

        // Контекст
        var ctx = (s.context || {}).model;
        if (ctx) {
            bar.appendChild(el('<span class="ab-sep"></span>'));
            var chip = el('<div class="ab-ctx"></div>');
            chip.appendChild(el('<span class="ab-ctx-text"><span class="ab-ctx-name">' + esc(ctx.component.name) + '</span><span class="ab-ctx-label" title="' + esc(ctx.label) + '">' + esc(ctx.label) + '</span></span>'));
            if (ctx.urls.update) {
                chip.appendChild(el('<a class="ab-btn is-accent" href="' + esc(ctx.urls.update) + '" data-tip="Редактировать запись">' + icon('edit', 16) + '<span class="ab-label">Редактировать</span></a>'));
            } else if (ctx.urls.view) {
                chip.appendChild(el('<a class="ab-btn" href="' + esc(ctx.urls.view) + '" data-tip="Открыть запись">' + icon('eye', 16) + '<span class="ab-label">Открыть</span></a>'));
            }
            chip.appendChild(el('<a class="ab-btn is-icon" href="' + esc(ctx.urls.index) + '" data-tip="Все: ' + esc(ctx.component.name) + '">' + icon('list', 16) + '</a>'));
            if (ctx.urls.create) {
                chip.appendChild(el('<a class="ab-btn is-icon" href="' + esc(ctx.urls.create) + '" data-tip="Добавить">' + icon('plus', 16) + '</a>'));
            }
            bar.appendChild(chip);
        }

        // Панели
        var panels = s.panels || [];
        if (panels.length) {
            bar.appendChild(el('<span class="ab-sep"></span>'));
            panels.forEach(function (p) {
                var b = el('<button type="button" class="ab-btn is-icon" data-tip="' + esc(p.label) + '">' + icon(p.icon || 'layers') + '</button>');
                this.attachPop(b, this.buildPanelPop.bind(this, p));
                bar.appendChild(b);
            }, this);
        }

        // Действия
        var actions = s.actions || [];
        if (actions.length) {
            bar.appendChild(el('<span class="ab-sep"></span>'));
            actions.forEach(function (a) {
                var b = el('<button type="button" class="ab-btn is-icon" data-tip="' + esc(a.label) + '">' + icon(a.icon || 'zap') + '</button>');
                b.addEventListener('click', this.runAction.bind(this, a, b));
                bar.appendChild(b);
            }, this);
        }

        // Inline-правка
        if ((s.features || {}).inlineEdit && document.querySelector('[data-ab-attr]')) {
            bar.appendChild(el('<span class="ab-sep"></span>'));
            this.editBtn = el('<button type="button" class="ab-btn" data-tip="Править текст на странице">' + icon('edit') + '<span class="ab-label">Править</span></button>');
            this.editBtn.addEventListener('click', this.toggleEditMode.bind(this));
            bar.appendChild(this.editBtn);
        }

        bar.appendChild(el('<span class="ab-sep"></span>'));

        // Обновление
        var badges = s.badges || {};
        if (badges.update) {
            bar.appendChild(el('<a class="ab-btn is-icon" href="' + esc(urls.update) + '" data-tip="Доступно обновление v' + esc(badges.update) + '">' + icon('update') + '<span class="ab-dot"></span></a>'));
        }

        // Тема
        var themeBtn = el('<button type="button" class="ab-btn is-icon ab-theme" data-tip="Тема">' + icon(this.root.dataset.theme === 'light' ? 'moon' : 'sun') + '</button>');
        themeBtn.addEventListener('click', this.toggleTheme.bind(this, themeBtn));
        bar.appendChild(themeBtn);

        // Свернуть
        var collapseBtn = el('<button type="button" class="ab-btn is-icon" data-tip="Свернуть (' + esc((s.prefs || {}).hotkey || 'Alt+Shift+A') + ')">' + icon('minus') + '</button>');
        collapseBtn.addEventListener('click', this.setCollapsed.bind(this, true));
        bar.appendChild(collapseBtn);

        this.root.appendChild(bar);
    };

    AdminBar.prototype.renderFab = function () {
        var hasBadge = (this.state.badges || {}).update;
        this.fab = el('<button type="button" class="ab-fab" title="Панель администратора">' + icon('command') + (hasBadge ? '<span class="ab-dot"></span>' : '') + '</button>');
        this.fab.addEventListener('click', this.setCollapsed.bind(this, false));
        this.root.appendChild(this.fab);
    };

    AdminBar.prototype.renderImpersonation = function () {
        var imp = this.state.impersonation || {};
        if (!imp.active) { return; }
        var u = this.state.user || {};
        this.root.appendChild(el('<div class="ab-impersonate">' + icon('alert', 16) + '<span>Вы смотрите сайт от имени пользователя ' + esc(u.name) + '</span><a href="' + esc(imp.returnUrl) + '">Вернуться в свой аккаунт</a></div>'));
    };

    /* ─── Поповеры ───────────────────────────────────────────── */
    AdminBar.prototype.attachPop = function (btn, builder) {
        var self = this;
        btn.style.position = 'relative';
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (self.openPop && self.openPop.btn === btn) { self.closePop(); return; }
            self.closePop();
            var pop = builder();
            pop.addEventListener('click', function (ev) { ev.stopPropagation(); });
            // Открываем поповер в родителе кнопки, чтобы не ломать overflow
            var wrap = el('<div style="position:relative;display:inline-flex"></div>');
            btn.parentNode.insertBefore(wrap, btn);
            wrap.appendChild(btn);
            wrap.appendChild(pop);
            // Если кнопка ближе к правому краю — прижимаем поповер вправо
            var rect = btn.getBoundingClientRect();
            if (rect.left + 300 > window.innerWidth) { pop.classList.add('is-right'); }
            btn.classList.add('is-active');
            self.openPop = { btn: btn, pop: pop, wrap: wrap };
        });
    };

    AdminBar.prototype.closePop = function () {
        if (!this.openPop) { return; }
        var o = this.openPop;
        o.btn.classList.remove('is-active');
        o.wrap.parentNode.insertBefore(o.btn, o.wrap);
        o.wrap.remove();
        this.openPop = null;
    };

    AdminBar.prototype.buildUserPop = function () {
        var u = this.state.user || {}, urls = this.state.urls || {};
        var avatar = u.avatar
            ? '<span class="ab-avatar"><img src="' + esc(u.avatar) + '" alt=""></span>'
            : '<span class="ab-avatar">' + esc(initials(u.name)) + '</span>';
        var pop = el('<div class="ab-pop"></div>');
        pop.appendChild(el('<div class="ab-pop-user">' + avatar + '<div><b>' + esc(u.name) + '</b><span>' + esc((u.roles || []).join(', ') || 'администратор') + '</span></div></div>'));
        pop.appendChild(el('<div class="ab-pop-sep"></div>'));
        pop.appendChild(el('<a class="ab-item" href="' + esc(urls.dashboard) + '">' + icon('home') + '<span class="ab-item-text">Панель управления</span></a>'));
        pop.appendChild(el('<a class="ab-item" href="' + esc(urls.profile) + '">' + icon('user') + '<span class="ab-item-text">Мой профиль</span></a>'));
        pop.appendChild(el('<a class="ab-item" href="' + esc(urls.settings) + '">' + icon('settings') + '<span class="ab-item-text">Настройки сайта</span></a>'));
        pop.appendChild(el('<div class="ab-pop-sep"></div>'));
        var pos = this.root.dataset.pos;
        var posBtn = el('<button type="button" class="ab-item">' + icon('layers') + '<span class="ab-item-text">Панель ' + (pos === 'top' ? 'вниз' : 'вверх') + '</span></button>');
        posBtn.addEventListener('click', this.togglePosition.bind(this));
        pop.appendChild(posBtn);
        pop.appendChild(el('<div class="ab-item" style="cursor:default">' + icon('keyboard') + '<span class="ab-item-text"><span class="ab-item-sub">Ctrl+K — поиск · ' + esc((this.state.prefs || {}).hotkey || 'Alt+Shift+A') + ' — свернуть</span></span></div>'));
        pop.appendChild(el('<div class="ab-pop-sep"></div>'));
        var logout = el('<button type="button" class="ab-item is-danger">' + icon('logout') + '<span class="ab-item-text">Выйти из админки</span></button>');
        logout.addEventListener('click', this.logout.bind(this));
        pop.appendChild(logout);
        pop.appendChild(el('<div class="ab-pop-sep"></div>'));
        pop.appendChild(el('<div class="ab-item" style="cursor:default"><span class="ab-item-sub">Yii2 Admin v' + esc(this.state.version) + '</span></div>'));
        return pop;
    };

    AdminBar.prototype.buildMenuPop = function () {
        var pop = el('<div class="ab-pop"><div class="ab-pop-head">Разделы админки</div></div>');
        var items = this.state.menu || [];
        if (!items.length) {
            pop.appendChild(el('<div class="ab-palette-empty">Меню пусто</div>'));
            return pop;
        }
        var self = this;
        items.forEach(function (item) {
            var hasChildren = item.children && item.children.length;
            if (!hasChildren) {
                pop.appendChild(el('<a class="ab-item" href="' + esc(item.href) + '" target="' + esc(item.target || '_self') + '">' + icon(self.menuIcon(item)) + '<span class="ab-item-text">' + esc(item.text) + '</span></a>'));
                return;
            }
            var group = el('<div class="ab-group"></div>');
            var head = el('<button type="button" class="ab-item">' + icon(self.menuIcon(item)) + '<span class="ab-item-text">' + esc(item.text) + '</span><svg class="ab-chev" viewBox="0 0 24 24">' + ICONS.chevron + '</svg></button>');
            head.addEventListener('click', function () { group.classList.toggle('is-open'); });
            group.appendChild(head);
            var ch = el('<div class="ab-group-children"></div>');
            item.children.forEach(function (c) {
                ch.appendChild(el('<a class="ab-item" href="' + esc(c.href) + '" target="' + esc(c.target || '_self') + '"><span class="ab-item-text">' + esc(c.text) + '</span></a>'));
            });
            group.appendChild(ch);
            pop.appendChild(group);
        });
        return pop;
    };

    AdminBar.prototype.menuIcon = function (item) {
        var fa = String(item.icon || '');
        if (/home/.test(fa)) { return 'home'; }
        if (/user/.test(fa)) { return 'users'; }
        if (/cog|setting|sliders/.test(fa)) { return 'settings'; }
        if (/envelope|mail/.test(fa)) { return 'mail'; }
        if (/file|news|book/.test(fa)) { return 'file'; }
        if (/folder|box|archive/.test(fa)) { return 'folder'; }
        if (/calendar|clock/.test(fa)) { return 'calendar'; }
        if (/search|seo/.test(fa)) { return 'search'; }
        if (/star|heart/.test(fa)) { return 'star'; }
        if (/link/.test(fa)) { return 'link'; }
        if (/shield|lock/.test(fa)) { return 'shield'; }
        if (/list|th/.test(fa)) { return 'list';}
        return 'layers';
    };

    AdminBar.prototype.buildPanelPop = function (panel) {
        var pop = el('<div class="ab-pop"><div class="ab-pop-head"><span>' + esc(panel.label) + '</span>' + (panel.url ? '<a class="ab-btn is-icon" style="height:26px;width:26px" href="' + esc(panel.url) + '" title="Открыть">' + icon('external', 14) + '</a>' : '') + '</div></div>');
        (panel.items || []).forEach(function (it) {
            var inner = icon(it.icon || 'info') + '<span class="ab-item-text">' + esc(it.label) + '</span>' + (it.value != null ? '<span class="ab-item-val">' + esc(it.value) + '</span>' : '');
            pop.appendChild(it.url
                ? el('<a class="ab-item" href="' + esc(it.url) + '">' + inner + '</a>')
                : el('<div class="ab-item">' + inner + '</div>'));
        });
        if (!(panel.items || []).length) {
            pop.appendChild(el('<div class="ab-palette-empty">Нет данных</div>'));
        }
        return pop;
    };

    /* ─── Действия ───────────────────────────────────────────── */
    AdminBar.prototype.runAction = function (action, btn) {
        var self = this;
        var go = function () {
            if (!action.server) {
                if (action.url) { window.open(action.url, action.target || '_self'); }
                return;
            }
            btn.disabled = true;
            self.post(self.state.endpoints.action, { id: action.id }).then(function (res) {
                btn.disabled = false;
                self.toast(res.message || (res.ok ? 'Готово' : 'Ошибка'), res.ok ? 'success' : 'error');
            }).catch(function () {
                btn.disabled = false;
                self.toast('Ошибка запроса', 'error');
            });
        };
        if (action.confirm) { this.confirm(action.confirm, go); } else { go(); }
    };

    AdminBar.prototype.post = function (url, data) {
        var fd = new FormData();
        var csrf = this.state.csrf || {};
        if (csrf.param) { fd.append(csrf.param, csrf.token); }
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k] == null ? '' : data[k]); });
        return fetch(url, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf.token || '' }
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: 'HTTP ' + r.status }; });
        });
    };

    AdminBar.prototype.logout = function () {
        var csrf = this.state.csrf || {};
        var form = document.createElement('form');
        form.method = 'post';
        form.action = this.state.urls.logout;
        form.innerHTML = '<input type="hidden" name="' + esc(csrf.param) + '" value="' + esc(csrf.token) + '">';
        document.body.appendChild(form);
        form.submit();
    };

    /* ─── Тосты и подтверждения ──────────────────────────────── */
    AdminBar.prototype.toast = function (text, type) {
        var t = el('<div class="ab-toast is-' + (type || 'info') + '">' + icon(type === 'error' ? 'alert' : 'check', 16) + '<span>' + esc(text) + '</span></div>');
        this.toasts.appendChild(t);
        setTimeout(function () { t.classList.add('is-out'); }, 2600);
        setTimeout(function () { t.remove(); }, 2900);
    };

    AdminBar.prototype.confirm = function (text, onOk) {
        var self = this;
        var box = el('<div class="ab-palette ab-confirm"><p>' + esc(text) + '</p><div class="ab-confirm-actions"><button type="button" class="ab-btn" data-r="no">Отмена</button><button type="button" class="ab-btn is-accent" data-r="yes">Да</button></div></div>');
        this.showOverlay(box);
        box.addEventListener('click', function (e) {
            var r = e.target.closest('[data-r]');
            if (!r) { return; }
            self.hideOverlay();
            if (r.dataset.r === 'yes') { onOk(); }
        });
        setTimeout(function () { box.querySelector('[data-r="yes"]').focus(); }, 0);
    };

    /* ─── Оверлей и палитра ──────────────────────────────────── */
    AdminBar.prototype.renderOverlay = function () {
        var self = this;
        this.overlay = el('<div class="ab-overlay"></div>');
        this.overlay.addEventListener('click', function (e) { if (e.target === self.overlay) { self.hideOverlay(); } });
        this.root.appendChild(this.overlay);
    };

    AdminBar.prototype.showOverlay = function (content) {
        this.closePop();
        this.overlay.innerHTML = '';
        this.overlay.appendChild(content);
        this.overlay.classList.add('is-open');
    };

    AdminBar.prototype.hideOverlay = function () {
        this.overlay.classList.remove('is-open');
        this.overlay.innerHTML = '';
    };

    AdminBar.prototype.paletteItems = function () {
        var s = this.state, out = [], urls = s.urls || {};
        var ctx = (s.context || {}).model;
        if (ctx) {
            if (ctx.urls.update) { out.push({ group: 'Эта страница', label: 'Редактировать: ' + ctx.label, sub: ctx.component.name, icon: 'edit', href: ctx.urls.update }); }
            out.push({ group: 'Эта страница', label: 'Все: ' + ctx.component.name, icon: 'list', href: ctx.urls.index });
            if (ctx.urls.create) { out.push({ group: 'Эта страница', label: 'Добавить: ' + ctx.component.name, icon: 'plus', href: ctx.urls.create }); }
        }
        var walk = function (items, path) {
            (items || []).forEach(function (it) {
                var p = path.concat([it.text]);
                if (it.href && it.href !== '#') { out.push({ group: 'Разделы', label: it.text, sub: path.join(' › '), icon: 'layers', href: it.href, target: it.target }); }
                if (it.children) { walk(it.children, p); }
            });
        };
        walk(s.menu, []);
        (s.actions || []).forEach(function (a) { out.push({ group: 'Действия', label: a.label, icon: a.icon || 'zap', action: a }); });
        out.push({ group: 'Админка', label: 'Панель управления', icon: 'home', href: urls.dashboard });
        out.push({ group: 'Админка', label: 'Настройки сайта', icon: 'settings', href: urls.settings });
        out.push({ group: 'Админка', label: 'Компоненты', icon: 'grid', href: urls.components });
        out.push({ group: 'Админка', label: 'Обновление админки', icon: 'update', href: urls.update });
        return out;
    };

    AdminBar.prototype.openPalette = function () {
        var self = this;
        var items = this.paletteItems();
        var box = el('<div class="ab-palette"><div class="ab-palette-input">' + icon('search') + '<input type="text" placeholder="Куда перейти или что сделать?" autocomplete="off"><kbd>Esc</kbd></div><div class="ab-palette-list"></div></div>');
        var input = box.querySelector('input'), list = box.querySelector('.ab-palette-list');
        var focus = 0, visible = [];

        var render = function () {
            var q = input.value.trim().toLowerCase();
            visible = items.filter(function (it) { return !q || (it.label + ' ' + (it.sub || '')).toLowerCase().indexOf(q) > -1; });
            focus = Math.min(focus, Math.max(0, visible.length - 1));
            list.innerHTML = '';
            if (!visible.length) { list.appendChild(el('<div class="ab-palette-empty">Ничего не найдено</div>')); return; }
            var lastGroup = null;
            visible.forEach(function (it, i) {
                if (it.group !== lastGroup) { list.appendChild(el('<div class="ab-palette-group">' + esc(it.group) + '</div>')); lastGroup = it.group; }
                var row = el('<button type="button" class="ab-item' + (i === focus ? ' is-focus' : '') + '">' + icon(it.icon) + '<span class="ab-item-text">' + esc(it.label) + (it.sub ? ' <span class="ab-item-sub">' + esc(it.sub) + '</span>' : '') + '</span></button>');
                row.addEventListener('click', function () { choose(it); });
                row.addEventListener('mousemove', function () { if (focus !== i) { focus = i; render(); } });
                list.appendChild(row);
            });
            var f = list.querySelector('.is-focus');
            if (f && f.scrollIntoView) { f.scrollIntoView({ block: 'nearest' }); }
        };
        var choose = function (it) {
            self.hideOverlay();
            if (it.action) { self.runAction(it.action, self.bar); return; }
            if (it.href) { window.open(it.href, it.target || '_self'); }
        };
        input.addEventListener('input', function () { focus = 0; render(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); focus = Math.min(focus + 1, visible.length - 1); render(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); focus = Math.max(focus - 1, 0); render(); }
            else if (e.key === 'Enter') { e.preventDefault(); if (visible[focus]) { choose(visible[focus]); } }
            else if (e.key === 'Escape') { self.hideOverlay(); }
        });
        this.showOverlay(box);
        render();
        setTimeout(function () { input.focus(); }, 0);
    };

    /* ─── Inline-правка ──────────────────────────────────────── */
    AdminBar.prototype.toggleEditMode = function () {
        this.editMode = !this.editMode;
        this.editBtn.classList.toggle('is-active', this.editMode);
        var id = 'admin-bar-edit-style';
        var style = document.getElementById(id);
        if (this.editMode) {
            if (!style) {
                style = document.createElement('style');
                style.id = id;
                style.textContent = '[data-ab-attr]{outline:2px dashed #3b82f6;outline-offset:3px;border-radius:3px;cursor:text;transition:background .15s}'
                    + '[data-ab-attr]:hover{background:rgba(59,130,246,.12)}'
                    + '[data-ab-attr][contenteditable="true"]{outline:2px solid #3b82f6;background:rgba(59,130,246,.08)}'
                    + '[data-ab-attr].ab-saving{opacity:.6}';
                document.head.appendChild(style);
            }
            this.bindEditables();
            this.toast('Кликните по подсвеченному тексту. Enter — сохранить, Esc — отмена', 'info');
        } else {
            if (style) { style.remove(); }
            this.unbindEditables();
        }
    };

    AdminBar.prototype.bindEditables = function () {
        var self = this;
        this._editHandler = function (e) {
            var node = e.target.closest('[data-ab-attr]');
            if (!node || node.isContentEditable) { return; }
            e.preventDefault();
            e.stopPropagation();
            self.startEdit(node);
        };
        document.addEventListener('click', this._editHandler, true);
    };

    AdminBar.prototype.unbindEditables = function () {
        if (this._editHandler) { document.removeEventListener('click', this._editHandler, true); }
    };

    AdminBar.prototype.startEdit = function (node) {
        var self = this;
        var type = node.dataset.abType || 'text';
        if (type !== 'text') { this.toast('Этот тип (' + type + ') пока редактируется только в админке', 'error'); return; }
        var original = node.textContent;
        node.setAttribute('contenteditable', 'true');
        node.focus();
        // курсор в конец
        try {
            var range = document.createRange(); range.selectNodeContents(node); range.collapse(false);
            var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
        } catch (e) { /* ignore */ }

        var done = false;
        var finish = function (save) {
            if (done) { return; }
            done = true;
            node.removeAttribute('contenteditable');
            node.removeEventListener('keydown', onKey);
            node.removeEventListener('blur', onBlur);
            var value = node.textContent.trim();
            if (!save || value === original.trim()) { node.textContent = original; return; }
            node.classList.add('ab-saving');
            self.post(self.state.endpoints.attribute, {
                model: node.dataset.abModel, id: node.dataset.abId, attr: node.dataset.abAttr, value: value
            }).then(function (res) {
                node.classList.remove('ab-saving');
                if (res.ok) {
                    node.textContent = res.value == null ? value : String(res.value);
                    self.toast((node.dataset.abLabel || 'Поле') + ': сохранено', 'success');
                } else {
                    node.textContent = original;
                    self.toast(res.message || 'Не удалось сохранить', 'error');
                }
            }).catch(function () {
                node.classList.remove('ab-saving');
                node.textContent = original;
                self.toast('Ошибка запроса', 'error');
            });
        };
        var onKey = function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); finish(true); }
            else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        };
        var onBlur = function () { finish(true); };
        node.addEventListener('keydown', onKey);
        node.addEventListener('blur', onBlur);
    };

    /* ─── Предпочтения ───────────────────────────────────────── */
    AdminBar.prototype.setCollapsed = function (flag) {
        this.closePop();
        this.root.classList.toggle('is-collapsed', flag);
        this.prefs.collapsed = flag;
        savePrefs(this.prefs);
    };

    AdminBar.prototype.toggleTheme = function (btn) {
        var next = this.root.dataset.theme === 'light' ? 'dark' : 'light';
        this.root.dataset.theme = next;
        btn.innerHTML = icon(next === 'light' ? 'moon' : 'sun');
        this.prefs.theme = next;
        savePrefs(this.prefs);
    };

    AdminBar.prototype.togglePosition = function () {
        this.closePop();
        var next = this.root.dataset.pos === 'top' ? 'bottom' : 'top';
        this.root.dataset.pos = next;
        this.prefs.position = next;
        savePrefs(this.prefs);
    };

    /* ─── Глобальные обработчики ─────────────────────────────── */
    AdminBar.prototype.bindGlobal = function () {
        var self = this;
        var hotkey = (this.state.prefs || {}).hotkey || 'Alt+Shift+A';
        document.addEventListener('click', function () { self.closePop(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { self.closePop(); if (self.overlay.classList.contains('is-open')) { self.hideOverlay(); } return; }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' && !self.editMode) {
                e.preventDefault();
                if (self.overlay.classList.contains('is-open')) { self.hideOverlay(); } else { self.openPalette(); }
                return;
            }
            if (matchHotkey(e, hotkey)) {
                e.preventDefault();
                self.setCollapsed(!self.root.classList.contains('is-collapsed'));
            }
        });
    };

    /* ─── Публичный API ──────────────────────────────────────── */
    window.AdminBar = {
        booted: false,
        instance: null,
        boot: function (state, assets) {
            if (window.AdminBar.booted) { return window.AdminBar.instance; }
            var app = new AdminBar(state, assets || state.assets || {});
            window.AdminBar.instance = app;
            if (document.body) { app.mount(); } else { document.addEventListener('DOMContentLoaded', function () { app.mount(); }); }
            return app;
        }
    };

    // server-режим: состояние уже на странице
    var stateEl = document.getElementById('admin-bar-state');
    if (stateEl) {
        try {
            var st = JSON.parse(stateEl.textContent);
            window.AdminBar.boot(st, st.assets);
        } catch (e) {
            if (window.console) { console.error('AdminBar: invalid state', e); }
        }
    }
})();
