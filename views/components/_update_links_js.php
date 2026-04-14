<?php
/**
 * JS для таба «Ссылки» — CRUD пула, drag-sort, модалка иконок.
 *
 * @category View
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 * @php      8.1
 */

declare(strict_types=1);

use yii\web\View;

/* @var $this yii\web\View */

$this->registerJs(
    <<<'JS'
(function () {
    'use strict';

    var POOL       = Array.isArray(window.adminLinksInitial) ? window.adminLinksInitial.slice() : [];
    var COLORS     = window.adminLinkColorsMap || {};
    var TARGETS    = window.adminLinkTargetsMap || {};
    var ICONS_SET  = new Set(window.adminLinkIconsList || []);
    var $hidden    = document.getElementById('adminmodel-links');
    var $list      = document.getElementById('links-editor-list');
    var $empty     = document.querySelector('.js-links-empty');
    var $iconModal = document.getElementById('linkIconModal');
    var iconModalInstance = $iconModal ? new bootstrap.Modal($iconModal) : null;
    var activeIconTargetId = null;

    window.adminLinksPool = POOL;

    function uid() {
        return 'lnk_' + Math.random().toString(36).slice(2, 10);
    }
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function findIdx(id) {
        for (var i = 0; i < POOL.length; i++) {
            if (POOL[i].id === id) return i;
        }
        return -1;
    }

    function renderPreview(link) {
        var cls = ['admin-link-btn', 'admin-link-btn--sm'];
        if (link.color && COLORS[link.color]) cls.push(link.color);
        var inner = '';
        if (link.icon && ICONS_SET.has(link.icon)) {
            inner += '<i class="' + esc(link.icon) + '"></i>';
        }
        if (link.title) {
            inner += (inner ? ' ' : '') + '<span>' + esc(link.title) + '</span>';
        }
        if (!inner) inner = '<i class="icon-link"></i>';
        return '<span class="' + cls.join(' ') + '">' + inner + '</span>';
    }

    function renderTargets(link) {
        var html = '';
        var groupName = 'link-target-' + esc(link.id);
        Object.keys(TARGETS).forEach(function (t) {
            html += '<label><input type="radio" data-role="target"'
                + ' name="' + groupName + '"'
                + ' value="' + t + '"'
                + (link.target === t ? ' checked' : '') + '> '
                + esc(TARGETS[t]) + '</label>';
        });
        return html;
    }

    function renderCard(link) {
        var colorSwatch = link.color && COLORS[link.color]
            ? '<span class="link-card__color-swatch" style="background:' + esc(COLORS[link.color]) + '"></span>'
            : '<span class="link-card__color-swatch" style="background:#fff"></span>';
        var iconLabel = link.icon && ICONS_SET.has(link.icon)
            ? '<i class="' + esc(link.icon) + '"></i>'
            : '—';
        return ''
            + '<div class="link-card" data-link-id="' + esc(link.id) + '">'
            + '  <div class="link-card__drag" title="Перетащить"><i class="icon-sliders"></i></div>'
            + '  <div class="link-card__body">'
            + '    <div class="link-card__row">'
            + '      <input type="text" data-role="title" placeholder="Название (необязательно)" value="' + esc(link.title || '') + '">'
            + '      <button type="button" class="link-card__icon-btn" data-role="icon-pick">'
            + '        <span data-role="icon-label">' + iconLabel + '</span>'
            + '        <span>иконка</span>'
            + '      </button>'
            + '      <button type="button" class="link-card__color-btn" data-role="color-pick">'
            + '        <span data-role="color-swatch">' + colorSwatch + '</span>'
            + '        <span>цвет</span>'
            + '      </button>'
            + '    </div>'
            + '    <div class="link-card__row">'
            + '      <input type="text" data-role="url" placeholder="https://example.com/?id={id}" value="' + esc(link.url || '') + '">'
            + '    </div>'
            + '    <div class="link-card__row link-card__targets">' + renderTargets(link) + '</div>'
            + '  </div>'
            + '  <div class="link-card__preview-wrap">'
            + '    <div class="link-card__preview" data-role="preview">' + renderPreview(link) + '</div>'
            + '    <button type="button" class="link-card__delete" data-role="remove">Удалить</button>'
            + '  </div>'
            + '</div>';
    }

    function renderAll() {
        $list.innerHTML = POOL.map(renderCard).join('');
        if ($empty) $empty.style.display = POOL.length ? 'none' : '';
        persist();
    }

    function refreshCard(id) {
        var idx = findIdx(id);
        if (idx < 0) return;
        var card = $list.querySelector('[data-link-id="' + id + '"]');
        if (!card) return;
        var link = POOL[idx];
        card.querySelector('[data-role="preview"]').innerHTML = renderPreview(link);
        var iconLabel = link.icon && ICONS_SET.has(link.icon)
            ? '<i class="' + esc(link.icon) + '"></i>'
            : '—';
        card.querySelector('[data-role="icon-label"]').innerHTML = iconLabel;
        var swatch = link.color && COLORS[link.color]
            ? '<span class="link-card__color-swatch" style="background:' + esc(COLORS[link.color]) + '"></span>'
            : '<span class="link-card__color-swatch" style="background:#fff"></span>';
        card.querySelector('[data-role="color-swatch"]').innerHTML = swatch;
    }

    function persist() {
        if ($hidden) $hidden.value = JSON.stringify(POOL);
        window.adminLinksPool = POOL;
        document.dispatchEvent(new CustomEvent('admin-links:changed', { detail: { pool: POOL } }));
    }

    /* ── Color popover (лёгкий inline) ─────────────────────────── */
    var $colorPop = null;
    function closeColorPop() {
        if ($colorPop && $colorPop.parentNode) $colorPop.parentNode.removeChild($colorPop);
        $colorPop = null;
    }
    document.addEventListener('click', function (e) {
        if ($colorPop && !$colorPop.contains(e.target)
            && !e.target.closest('[data-role="color-pick"]')) {
            closeColorPop();
        }
    });
    function openColorPop(anchor, link) {
        closeColorPop();
        $colorPop = document.createElement('div');
        $colorPop.className = 'wg-box';
        $colorPop.style.position = 'absolute';
        $colorPop.style.zIndex   = '1060';
        $colorPop.style.padding  = '6px';
        var html = '<div class="link-color-palette">';
        Object.keys(COLORS).forEach(function (k) {
            html += '<div class="link-color-palette__item'
                 + (link.color === k ? ' is-active' : '') + '"'
                 + ' style="background:' + COLORS[k] + '"'
                 + ' data-color="' + k + '" title="' + k + '"></div>';
        });
        html += '<div class="link-color-palette__none" data-color="">Без цвета</div>';
        html += '</div>';
        $colorPop.innerHTML = html;
        document.body.appendChild($colorPop);
        var r = anchor.getBoundingClientRect();
        $colorPop.style.top  = (window.scrollY + r.bottom + 4) + 'px';
        $colorPop.style.left = (window.scrollX + r.left) + 'px';

        $colorPop.addEventListener('click', function (ev) {
            var el = ev.target.closest('[data-color]');
            if (!el) return;
            link.color = el.getAttribute('data-color');
            refreshCard(link.id);
            persist();
            closeColorPop();
        });
    }

    /* ── События карточек ─────────────────────────────────────── */
    $list.addEventListener('input', function (e) {
        var card = e.target.closest('.link-card'); if (!card) return;
        var idx  = findIdx(card.getAttribute('data-link-id')); if (idx < 0) return;
        var role = e.target.getAttribute('data-role');
        if (role === 'title' || role === 'url') {
            POOL[idx][role] = e.target.value;
            if (role === 'title') refreshCard(POOL[idx].id);
            persist();
        }
    });
    $list.addEventListener('change', function (e) {
        var card = e.target.closest('.link-card'); if (!card) return;
        var idx  = findIdx(card.getAttribute('data-link-id')); if (idx < 0) return;
        if (e.target.getAttribute('data-role') === 'target') {
            POOL[idx].target = e.target.value;
            persist();
        }
    });

    /*
     * Принудительное переключение radio: SortableJS/тема могут
     * перехватывать click/mousedown на input, из-за чего браузер не
     * обновляет checked. Слушаем mousedown+click в capture и сами ставим.
     */
    function forceRadioSelect(e) {
        var input = null;
        if (e.target && e.target.matches
            && e.target.matches('input[type="radio"][data-role="target"]')
        ) {
            input = e.target;
        }
        if (!input) {
            var lbl = e.target.closest
                && e.target.closest('.link-card__targets label');
            if (!lbl) return;
            input = lbl.querySelector('input[type="radio"][data-role="target"]');
        }
        if (!input) return;

        var card = input.closest('.link-card'); if (!card) return;
        var group = input.name;
        card.querySelectorAll(
            'input[type="radio"][name="' + group + '"]'
        ).forEach(function (r) { r.checked = (r === input); });

        var idx = findIdx(card.getAttribute('data-link-id'));
        if (idx < 0) return;
        if (POOL[idx].target !== input.value) {
            POOL[idx].target = input.value;
            persist();
        }
    }
    $list.addEventListener('mousedown', forceRadioSelect, true);
    $list.addEventListener('click', forceRadioSelect, true);
    $list.addEventListener('click', function (e) {
        var card = e.target.closest('.link-card'); if (!card) return;
        var idx  = findIdx(card.getAttribute('data-link-id')); if (idx < 0) return;
        var link = POOL[idx];

        var pickIcon = e.target.closest('[data-role="icon-pick"]');
        if (pickIcon) {
            activeIconTargetId = link.id;
            document.querySelectorAll('#linkIconGrid .link-icon-grid__item')
                .forEach(function (el) {
                    el.classList.toggle('is-active', el.getAttribute('data-icon') === link.icon);
                });
            if (iconModalInstance) iconModalInstance.show();
            return;
        }
        var pickColor = e.target.closest('[data-role="color-pick"]');
        if (pickColor) { openColorPop(pickColor, link); return; }

        if (e.target.closest('[data-role="remove"]')) {
            if (!confirm('Удалить ссылку из набора?')) return;
            POOL.splice(idx, 1);
            renderAll();
            return;
        }
    });

    /* ── Модалка иконок ───────────────────────────────────────── */
    if ($iconModal) {
        $iconModal.addEventListener('click', function (e) {
            var item = e.target.closest('.link-icon-grid__item');
            if (item && activeIconTargetId) {
                var idx = findIdx(activeIconTargetId);
                if (idx >= 0) {
                    POOL[idx].icon = item.getAttribute('data-icon');
                    refreshCard(POOL[idx].id);
                    persist();
                }
                if (iconModalInstance) iconModalInstance.hide();
            }
            if (e.target.closest('.js-link-icon-clear') && activeIconTargetId) {
                var i2 = findIdx(activeIconTargetId);
                if (i2 >= 0) {
                    POOL[i2].icon = '';
                    refreshCard(POOL[i2].id);
                    persist();
                }
                if (iconModalInstance) iconModalInstance.hide();
            }
        });
    }

    /* ── Добавление ───────────────────────────────────────────── */
    var $addBtn = document.querySelector('.js-link-add');
    if ($addBtn) {
        $addBtn.addEventListener('click', function () {
            POOL.push({
                id: uid(), title: '', icon: '', color: '',
                url: '', target: '_blank'
            });
            renderAll();
        });
    }

    /* ── Sortable для карточек ───────────────────────────────── */
    if (window.Sortable) {
        new Sortable($list, {
            animation: 150,
            handle: '.link-card__drag',
            draggable: '.link-card',
            filter: 'input, label, button, textarea, select, a',
            preventOnFilter: false,
            onEnd: function () {
                var order = Array.from($list.children).map(function (el) {
                    return el.getAttribute('data-link-id');
                });
                POOL.sort(function (a, b) {
                    return order.indexOf(a.id) - order.indexOf(b.id);
                });
                persist();
            }
        });
    }

    renderAll();
})();
JS
    ,
    View::POS_END
);
?>
