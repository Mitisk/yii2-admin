/**
 * Admin Bar — правка текстовых блоков раздела «Контент».
 * Грузится лениво из bar.js при первой правке блока.
 *
 *  - text: прямо на странице (Enter — сохранить, Esc — отмена) через POST endpoints.block;
 *  - html, image, link, list: модальное окно с формой админки в iframe; после сохранения
 *    форма шлёт postMessage, модуль перечитывает страницу и подменяет блок без перезагрузки.
 */
(function () {
    'use strict';

    function Blocks(bar) {
        this.bar = bar;
        this.state = bar.state;
    }

    Blocks.prototype.edit = function (node) {
        if (node.dataset.abType === 'text') { this.inline(node); } else { this.modal(node); }
    };

    Blocks.prototype.inline = function (node) {
        var self = this, bar = this.bar;
        if (node.isContentEditable) { return; }
        var original = node.textContent;
        node.setAttribute('contenteditable', 'true');
        node.focus();
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
            bar.post(self.state.endpoints.block, { key: node.dataset.abBlock, value: value }).then(function (res) {
                node.classList.remove('ab-saving');
                node.textContent = res.ok ? String(res.value == null ? value : res.value) : original;
                bar.toast(res.ok ? (node.dataset.abLabel || 'Блок') + ': сохранено' : (res.message || 'Не удалось сохранить'), res.ok ? 'success' : 'error');
            }).catch(function () {
                node.classList.remove('ab-saving');
                node.textContent = original;
                bar.toast('Ошибка запроса', 'error');
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

    Blocks.prototype.modal = function (node) {
        var self = this, bar = this.bar, key = node.dataset.abBlock;
        var box = document.createElement('div');
        box.className = 'ab-palette';
        box.style.cssText = 'width:min(920px,calc(100vw - 32px));height:min(80vh,760px);display:flex;flex-direction:column;padding:0;overflow:hidden';
        box.innerHTML = '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px">'
            + '<b></b><button type="button" class="ab-btn is-icon" data-close title="Закрыть">✕</button></div>'
            + '<iframe style="flex:1;border:0;background:#fff" title="Редактирование блока"></iframe>';
        box.querySelector('b').textContent = node.dataset.abLabel || key;
        box.querySelector('iframe').src = this.state.urls.blockEdit + encodeURIComponent(key);
        box.querySelector('[data-close]').addEventListener('click', function () { bar.hideOverlay(); });

        var onMessage = function (e) {
            if (e.origin !== window.location.origin || !e.data || e.data.type !== 'ab-block-saved' || e.data.key !== key) { return; }
            window.removeEventListener('message', onMessage);
            bar.hideOverlay();
            bar.toast((node.dataset.abLabel || 'Блок') + ': сохранено', 'success');
            self.refresh(key);
        };
        window.addEventListener('message', onMessage);
        bar.showOverlay(box);
    };

    /* Перечитать страницу и подменить блоки с этим ключом; разметку блока задаёт сайт */
    Blocks.prototype.refresh = function (key) {
        var sel = '[data-ab-block="' + (window.CSS && CSS.escape ? CSS.escape(key) : key) + '"]';
        fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'AdminBar' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelectorAll(sel), cur = document.querySelectorAll(sel);
                if (fresh.length !== cur.length) { window.location.reload(); return; }
                Array.prototype.forEach.call(cur, function (n, i) { n.replaceWith(document.importNode(fresh[i], true)); });
            })
            .catch(function () { window.location.reload(); });
    };

    window.AdminBarBlocks = {
        init: function (bar) { return new Blocks(bar); }
    };
})();
