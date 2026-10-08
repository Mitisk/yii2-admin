/**
 * Admin Bar loader (client-режим): страница одинакова для всех,
 * панель запрашивает состояние сама; неадмину сервер отвечает 401.
 */
(function () {
    'use strict';
    var s = document.currentScript;
    if (!s || !s.dataset.state) { return; }
    var url = s.dataset.state
        + (s.dataset.state.indexOf('?') > -1 ? '&' : '?')
        + 'url=' + encodeURIComponent(location.pathname + location.search)
        + (s.dataset.ctx ? '&ctx=' + encodeURIComponent(s.dataset.ctx) : '');
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (state) {
            if (!state || !state.user) { return; }
            if (s.dataset.position) { state.prefs = state.prefs || {}; state.prefs.position = s.dataset.position; }
            var sc = document.createElement('script');
            sc.src = s.dataset.js;
            sc.onload = function () { window.AdminBar.boot(state, { css: s.dataset.css }); };
            document.head.appendChild(sc);
        })
        .catch(function () { /* панель не обязательна */ });
})();
