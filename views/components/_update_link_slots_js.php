<?php
/**
 * JS для пула ссылок и инлайн-редактора слот-колонок в табе Grid.
 *
 * @category View
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 * @php      8.1
 */

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\View;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminModel */

$this->registerJsVar(
    'adminLinkSlotsInputPrefix',
    Html::getInputName($model, 'list')
);

$this->registerJs(
    <<<'JS'
(function () {
    'use strict';

    var COLORS    = window.adminLinkColorsMap || {};
    var ICONS_SET = new Set(window.adminLinkIconsList || []);
    var INPUT_PFX = window.adminLinkSlotsInputPrefix || 'AdminModel[list]';

    var $gridSort = document.querySelector('.js-gc-sortable');
    var $pool     = document.getElementById('links-pool');
    var $addBtn   = document.querySelector('.js-link-slot-add');

    if (!$gridSort) return;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function uid() {
        return 'admin_link_' + Math.random().toString(36).slice(2, 8);
    }
    function getPool() {
        return Array.isArray(window.adminLinksPool)
            ? window.adminLinksPool : [];
    }
    function findLink(id) {
        var p = getPool();
        for (var i = 0; i < p.length; i++) if (p[i].id === id) return p[i];
        return null;
    }
    function linkPreview(link) {
        if (!link) {
            return '<span class="admin-link-btn admin-link-btn--sm pastel-red">'
                + '<i class="icon-x"></i><span>удалена</span></span>';
        }
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

    /* ── Пул ссылок (drag source) ──────────────────────────── */
    function renderPool() {
        if (!$pool) return;
        var p = getPool();
        $pool.innerHTML = p.map(function (link) {
            return '<span class="link-slot-chip" draggable="true"'
                + ' data-link-id="' + esc(link.id) + '">'
                + linkPreview(link) + '</span>';
        }).join('');
    }

    /* ── Обновление chip preview в drop-зонах ──────────────── */
    function refreshAllChipPreviews() {
        $gridSort.querySelectorAll(
            '.link-slot-row .link-slot-chip'
        ).forEach(function (chip) {
            var prev = chip.querySelector('[data-role="chip-preview"]');
            if (!prev) {
                // chip из пула, перетащенный через Sortable — reшрейпим
                var id = chip.getAttribute('data-link-id');
                chip.innerHTML = '<span class="link-slot-chip__preview"'
                    + ' data-role="chip-preview">'
                    + linkPreview(findLink(id)) + '</span>'
                    + '<span class="remove" data-role="chip-remove">×</span>';
                return;
            }
            var id2 = chip.getAttribute('data-link-id');
            prev.innerHTML = linkPreview(findLink(id2));
        });
    }

    /* ── Hidden inputs для строки слота ────────────────────── */
    function rebuildHiddenInputs(row) {
        var key = row.getAttribute('data-link-slot');
        var p   = INPUT_PFX + '[' + key + ']';
        var box = row.querySelector('[data-role="slot-inputs"]');
        if (!box) return;
        var nameInput = row.querySelector('[data-role="slot-name"]');
        var nameVal   = nameInput ? nameInput.value : 'Ссылки';

        var chips = row.querySelectorAll(
            '[data-role="slot-drop"] .link-slot-chip'
        );
        var html = ''
            + '<input type="hidden" name="' + p + '[type]" value="links">'
            + '<input type="hidden" name="' + p + '[name]"'
            + ' data-role="hidden-name" value="' + esc(nameVal) + '">';
        if (!chips.length) {
            html += '<input type="hidden" name="' + p + '[items][]" value="">';
        } else {
            chips.forEach(function (chip) {
                html += '<input type="hidden" name="' + p + '[items][]" value="'
                    + esc(chip.getAttribute('data-link-id')) + '">';
            });
        }
        box.innerHTML = html;
    }

    /* ── Построение новой строки-слота ─────────────────────── */
    function buildSlotRow(key, slotName) {
        var row = document.createElement('div');
        row.className = 'grid-column-item list-draggable link-slot-row';
        row.setAttribute('data-link-slot', key);
        row.innerHTML = ''
            + '<div style="display:flex;align-items:center;flex:1;'
            + 'min-width:0;gap:10px;">'
            + '  <div class="drag-area" style="padding-right:12px;cursor:grab;">'
            + '    <i class="fas fa-grip-vertical"'
            + '       style="color:#cbd5e1;font-size:18px;"></i>'
            + '  </div>'
            + '  <span class="body-title"'
            + '     style="font-size:13px;color:#3b82f6;white-space:nowrap;">'
            + '    <i class="fas fa-link me-1"></i>'
            + '  </span>'
            + '  <input type="text" class="link-slot-row__name"'
            + '    data-role="slot-name" value="' + esc(slotName) + '"'
            + '    placeholder="Название колонки"'
            + '    style="flex:0 0 180px;padding:4px 8px;'
            + '    border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">'
            + '  <div class="link-slot-row__drop" data-role="slot-drop"'
            + '    style="flex:1;min-height:34px;border:1px dashed #cbd5e1;'
            + '    border-radius:6px;padding:4px;display:flex;'
            + '    flex-wrap:wrap;gap:4px;background:#fff;"></div>'
            + '  <button type="button" class="link-card__delete"'
            + '    data-role="slot-remove" title="Удалить слот">'
            + '    <i class="fas fa-times"></i></button>'
            + '  <div class="link-slot-row__inputs"'
            + '    data-role="slot-inputs"></div>'
            + '</div>'
            + '<div style="display:flex;align-items:center;gap:8px;'
            + 'margin-left:12px;">'
            + '  <input class="total-checkbox" type="checkbox" value="1" checked'
            + '    id="gc-' + esc(key) + '"'
            + '    name="' + INPUT_PFX + '[' + esc(key) + '][on]">'
            + '</div>';
        return row;
    }

    /* ── Sortable drop-zones + pool ────────────────────────── */
    var sortableInstances = [];
    function destroySortables() {
        sortableInstances.forEach(function (s) {
            try { s.destroy(); } catch (_) {}
        });
        sortableInstances = [];
    }
    function initSortables() {
        if (!window.Sortable) return;
        destroySortables();
        if ($pool) {
            sortableInstances.push(new Sortable($pool, {
                group: { name: 'admin-links', pull: 'clone', put: false },
                sort: false, animation: 150
            }));
        }
        $gridSort.querySelectorAll('[data-role="slot-drop"]')
            .forEach(function (dz) {
                sortableInstances.push(new Sortable(dz, {
                    group: { name: 'admin-links', pull: false, put: true },
                    animation: 150,
                    onAdd: function (evt) {
                        var row = dz.closest('.link-slot-row');
                        var chip = evt.item;
                        var id   = chip.getAttribute('data-link-id');
                        // запрет дубликата в одном слоте
                        var dup = dz.querySelectorAll(
                            '.link-slot-chip[data-link-id="' + id + '"]'
                        );
                        if (dup.length > 1) { chip.remove(); return; }
                        // нормализуем внутренности
                        chip.innerHTML = '<span class="link-slot-chip__preview"'
                            + ' data-role="chip-preview">'
                            + linkPreview(findLink(id)) + '</span>'
                            + '<span class="remove"'
                            + ' data-role="chip-remove">×</span>';
                        rebuildHiddenInputs(row);
                    },
                    onSort: function () {
                        var row = dz.closest('.link-slot-row');
                        rebuildHiddenInputs(row);
                    }
                }));
            });
    }

    /* ── Делегирование событий по строкам слотов ───────────── */
    $gridSort.addEventListener('input', function (e) {
        var row = e.target.closest('.link-slot-row'); if (!row) return;
        if (e.target.getAttribute('data-role') === 'slot-name') {
            var hn = row.querySelector('[data-role="hidden-name"]');
            if (hn) hn.value = e.target.value;
        }
    });
    $gridSort.addEventListener('click', function (e) {
        var row = e.target.closest('.link-slot-row'); if (!row) return;

        if (e.target.closest('[data-role="slot-remove"]')) {
            if (!confirm('Удалить колонку-слот?')) return;
            row.parentNode.removeChild(row);
            initSortables();
            return;
        }
        var rm = e.target.closest('[data-role="chip-remove"]');
        if (rm) {
            var chip = rm.closest('.link-slot-chip');
            if (chip && chip.parentNode) chip.parentNode.removeChild(chip);
            rebuildHiddenInputs(row);
        }
    });

    /* ── Кнопка «Добавить слот» ────────────────────────────── */
    if ($addBtn) {
        $addBtn.addEventListener('click', function () {
            var key = uid();
            var row = buildSlotRow(key, 'Ссылки');
            $gridSort.appendChild(row);
            rebuildHiddenInputs(row);
            initSortables();
            // включаем arrangeable на новом элементе
            if (window.jQuery
                && typeof window.jQuery(row).arrangeable === 'function'
            ) {
                window.jQuery(row).arrangeable({ dragSelector: '.drag-area' });
            }
        });
    }

    /* ── Реакция на изменения пула ссылок ─────────────────── */
    document.addEventListener('admin-links:changed', function () {
        renderPool();
        refreshAllChipPreviews();
        initSortables();
    });

    /* Инициализация */
    renderPool();
    refreshAllChipPreviews();
    initSortables();
})();
JS
    ,
    View::POS_END
);
?>
