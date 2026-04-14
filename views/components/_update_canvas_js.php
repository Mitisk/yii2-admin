<?php
/**
 * Canvas JS partial — registers JS vars and the visual canvas script.
 *
 * @category View
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 *
 * @var \yii\web\View                          $this
 * @var \Mitisk\Yii2Admin\models\AdminModel    $model
 * @var string                                 $publicStaticMethods
 * @var string                                 $publicSaveMethods
 * @var \yii\rbac\Role[]                       $roles
 * @var array                                  $allDbAttributesForJs
 * @var array                                  $allPublicAttributesForJs
 *
 * @php 8.0
 */

use yii\helpers\Json;
use yii\web\View;

// Передаём данные в JS
$this->registerJsVar('formData', $model->data ?: '[]', View::POS_END);
$this->registerJsVar('publicStaticMethods', Json::decode($publicStaticMethods) ?: [], View::POS_END);
$this->registerJsVar('publicSaveMethods', Json::decode($publicSaveMethods) ?: [], View::POS_END);
$rolesMap = [];
foreach ($roles as $role) {
    $rolesMap[$role->name] = $role->description;
}
$this->registerJsVar('roles', $rolesMap, View::POS_END);
$this->registerJsVar(
    'allDbAttributesData', $allDbAttributesForJs, View::POS_END
);
$this->registerJsVar(
    'allPublicAttributesData', $allPublicAttributesForJs, View::POS_END
);
?>

<?php $this->registerJs(
    <<<'JS'
/* ─── Visual Canvas ──────────────────────────────────────────────────────── */
(function () {
    'use strict';

    if (!document.getElementById('form-canvas')) return; // Холст не нужен на шаге 1

    /* ─── Утилиты ─────────────────────────────────────────── */
    const genId = () => 'f_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

    const ICON_MAP = {
        text: 'fa-font', textarea: 'fa-align-left',
        html: 'fa-code', visual: 'fa-palette',
        select: 'fa-list', file: 'fa-image', date: 'fa-calendar',
        posted: 'fa-check-square', number: 'fa-hashtag', hidden: 'fa-lock',
        user: 'fa-user',
    };

    /* ─── Состояние ───────────────────────────────────────── */
    const allFields       = Array.isArray(allDbAttributesData) ? allDbAttributesData : [];
    const allPublicFields = Array.isArray(allPublicAttributesData) ? allPublicAttributesData : [];

    let parsedFormData = [];
    try {
        const raw = Array.isArray(formData) ? formData : JSON.parse(formData);
        if (Array.isArray(raw)) parsedFormData = raw;
    } catch (_) {}

    // Нормализуем сохранённые элементы, добавляем id если нет
    let canvasItems = parsedFormData.filter(Boolean).map(item => Object.assign({
        id: genId(), isContent: false, width: '100',
        label: '', type: 'text', required: false, readonly: false, hint: '',
        withTime: false,
        roles: [], selectMultiple: false,
        selectSourceType: 'method', selectSourceVal: '', selectSaveMethod: '',
        tag: undefined, text: undefined,
    }, item, { id: item.id || genId() }));

    // Поля, ещё не добавленные на холст
    const usedNames = () => new Set(canvasItems.filter(i => !i.isContent).map(i => i.name));
    const getAvailable       = () => allFields.filter(f => !usedNames().has(f.name));
    const getAvailablePublic = () => allPublicFields.filter(f => !usedNames().has(f.name));

    let activeId = null;

    /* ─── DOM-ссылки ──────────────────────────────────────── */
    const elAvail   = document.getElementById('canvas-available-fields');
    const elPublic  = document.getElementById('canvas-public-fields');
    const elContent = document.getElementById('canvas-content-fields');
    const elLinks   = document.getElementById('canvas-link-fields');
    const elCanvas  = document.getElementById('form-canvas');
    const elEmpty   = document.getElementById('canvas-empty');
    const elPanel   = document.getElementById('canvas-props-panel');
    const elHidden  = document.getElementById('adminmodel-data');

    /* ─── Инициализация ролей ─────────────────────────────── */
    function buildRolesGrid() {
        const grid = document.getElementById('canvas-roles-grid');
        if (!grid) return;
        grid.innerHTML = Object.entries(roles || {}).map(([name, desc]) =>
            `<label><input type="checkbox" class="role-cb" value="${name}" onchange="canvasUpdateField()"> ${desc || name}</label>`
        ).join('');
    }

    /* ─── Инициализация Select-источников ────────────────── */
    function buildSelectSources() {
        const selSrc  = document.getElementById('prop-sel-source-val');
        const selSave = document.getElementById('prop-sel-save-method');
        if (!selSrc || !selSave) return;

        Object.entries(publicStaticMethods || {}).forEach(([key, label]) => {
            if (key) selSrc.appendChild(new Option(label, key));
        });
        Object.entries(publicSaveMethods || {}).forEach(([key, label]) => {
            if (key) selSave.appendChild(new Option(label, key));
        });
    }

    /* ─── Рендер ──────────────────────────────────────────── */
    function _makePill(field, source) {
        const pill = document.createElement('div');
        const isPublic = source === 'public';
        pill.className = 'canvas-field-pill' + (isPublic ? ' canvas-field-pill--public' : '');
        pill.dataset.name   = field.name;
        pill.dataset.source = source;
        const icon = isPublic ? 'fa-cube' : (ICON_MAP[field.type] || 'fa-keyboard');
        const iconCls = isPublic ? 'text-info' : 'text-secondary';
        pill.innerHTML =
            `<i class="fas fa-grip-vertical drag-icon"></i>` +
            `<span><i class="fas ${icon} ${iconCls} me-1"></i>` +
            ` ${field.label} <small style="color:#94a3b8;">(${field.name})</small></span>`;
        return pill;
    }

    function renderAvailable() {
        elAvail.innerHTML = '';
        getAvailable().forEach(f => elAvail.appendChild(_makePill(f, 'db')));

        if (elPublic) {
            elPublic.innerHTML = '';
            getAvailablePublic().forEach(f => elPublic.appendChild(_makePill(f, 'public')));
        }

        renderLinkPalette();
    }

    function _linkPreviewHtml(link) {
        if (!link) {
            return '<span class="admin-link-btn admin-link-btn--sm pastel-red">'
                + '<i class="icon-x"></i><span>удалена</span></span>';
        }
        const colors = window.adminLinkColorsMap || {};
        const icons  = new Set(window.adminLinkIconsList || []);
        let cls = 'admin-link-btn admin-link-btn--sm';
        if (link.color && colors[link.color]) cls += ' ' + link.color;
        let inner = '';
        if (link.icon && icons.has(link.icon)) {
            inner += `<i class="${link.icon}"></i>`;
        }
        if (link.title) {
            inner += (inner ? ' ' : '') + `<span>${link.title}</span>`;
        }
        if (!inner) inner = '<i class="icon-link"></i>';
        return `<span class="${cls}">${inner}</span>`;
    }

    function renderLinkPalette() {
        if (!elLinks) return;
        const pool = Array.isArray(window.adminLinksPool)
            ? window.adminLinksPool : [];
        elLinks.innerHTML = '';
        if (!pool.length) {
            elLinks.innerHTML = '<div class="body-text"'
                + ' style="font-size:12px;color:#94a3b8;">'
                + 'Нет ссылок. Создайте их во вкладке «Ссылки».</div>';
            return;
        }
        pool.forEach(link => {
            const pill = document.createElement('div');
            pill.className = 'canvas-field-pill';
            pill.dataset.isLink = 'true';
            pill.dataset.linkId = link.id;
            pill.innerHTML =
                '<i class="fas fa-grip-vertical drag-icon"></i>'
                + '<span>' + _linkPreviewHtml(link) + '</span>';
            elLinks.appendChild(pill);
        });
    }

    function renderCanvas() {
        // Удаляем все элементы кроме empty-state
        Array.from(elCanvas.children).forEach(c => { if (c !== elEmpty) c.remove(); });

        elEmpty.style.display = canvasItems.length === 0 ? 'block' : 'none';

        canvasItems.forEach(item => {
            const el = document.createElement('div');
            const wCls = 'cw-' + (item.width || '100');
            const activeCls = activeId === item.id ? 'is-active' : '';
            const contentCls = item.isContent ? `content-${item.type}` : '';
            el.className = `canvas-item ${wCls} ${activeCls} ${contentCls}`.trim();
            el.dataset.id = item.id;

            let inner = '';
            if (item.isContent) {
                if (item.type === 'header')    inner = `<${item.tag || 'h2'}>${item.text || 'Заголовок'}</${item.tag || 'h2'}>`;
                if (item.type === 'paragraph') inner = `<p style="margin:0;">${item.text || 'Текст абзаца...'}</p>`;
                if (item.type === 'divider')   inner = '';
                if (item.type === 'link') {
                    const pool = Array.isArray(window.adminLinksPool)
                        ? window.adminLinksPool : [];
                    const link = pool.find(l => l.id === item.link_id) || null;
                    el.dataset.type = 'link';
                    if (!link) el.dataset.linkDeleted = '1';
                    inner = '<div style="display:flex;align-items:center;gap:8px;">'
                        + _linkPreviewHtml(link)
                        + '<small style="color:#64748b;">(ссылка-кнопка)</small>'
                        + '</div>';
                }
            } else {
                const icon = ICON_MAP[item.type] || 'fa-keyboard';
                const reqBadge = item.required ? `<span class="canvas-req-badge">Req</span>` : '';
                const rolesBadge = (item.roles && item.roles.length)
                    ? `<span class="badge bg-info text-dark" style="font-size:11px;"><i class="fas fa-users"></i></span>` : '';
                inner = `
                    <div class="canvas-item-label">
                        <i class="fas ${icon} text-secondary"></i> ${item.label || item.name}
                        ${reqBadge} ${rolesBadge}
                    </div>
                    <div class="canvas-item-meta">
                        <span>DB: <code>${item.name}</code></span>
                        <span><i class="fas fa-arrows-alt-h mx-1"></i>${item.width || 100}%</span>
                    </div>`;
            }

            el.innerHTML =
                `<i class="fas fa-grip-vertical drag-icon fs-6"></i>` +
                `<div class="canvas-item-body">${inner}</div>` +
                `<button type="button" class="canvas-item-remove" onclick="canvasRemove('${item.id}')">` +
                `<i class="fas fa-times"></i></button>`;

            el.addEventListener('click', e => {
                if (!e.target.closest('.canvas-item-remove')) canvasOpenProps(item.id);
            });

            elCanvas.appendChild(el);
        });
    }

    function renderAll() {
        renderAvailable();
        renderCanvas();
        syncHidden();
    }

    function syncHidden() {
        if (elHidden) elHidden.value = JSON.stringify(canvasItems);
    }

    /* ─── Drag & Drop (SortableJS) ───────────────────────── */
    function initSortable() {
        if (typeof Sortable === 'undefined') return;

        const shared = {
            group: { name: 'canvas', pull: 'clone', put: false },
            animation: 150, sort: false, handle: '.drag-icon',
        };

        new Sortable(elAvail, { ...shared,
            onEnd(evt) { if (evt.to === elCanvas) handleDrop(evt, false); }
        });
        if (elPublic) {
            new Sortable(elPublic, { ...shared,
                onEnd(evt) { if (evt.to === elCanvas) handleDrop(evt, false); }
            });
        }
        new Sortable(elContent, { ...shared,
            onEnd(evt) { if (evt.to === elCanvas) handleDrop(evt, true); }
        });

        if (elLinks) {
            new Sortable(elLinks, { ...shared,
                onEnd(evt) {
                    if (evt.to === elCanvas) handleDrop(evt, true, true);
                }
            });
        }

        new Sortable(elCanvas, {
            group: 'canvas', animation: 150, handle: '.drag-icon',
            ghostClass: 'sortable-ghost', filter: '#canvas-empty',
            onUpdate(evt) {
                // #canvas-empty is always the first DOM child (even when hidden),
                // so SortableJS indices are always +1 relative to canvasItems.
                const moved = canvasItems.splice(evt.oldIndex - 1, 1)[0];
                canvasItems.splice(evt.newIndex - 1, 0, moved);
                syncHidden();
            },
            onAdd(evt) {
                // Удаляем клон который добавил Sortable — мы рендерим сами
                evt.item.remove();
            },
        });
    }

    function handleDrop(evt, isContent, isLink) {
        evt.item.remove(); // удаляем клон из DOM

        const offset = elEmpty.style.display === 'none' ? 0 : 1;
        const insertIdx = Math.max(0, evt.newIndex - offset);

        let newItem = { id: genId(), isContent, width: '100' };

        if (isLink) {
            newItem.type    = 'link';
            newItem.link_id = evt.item.dataset.linkId;
            var _pool = Array.isArray(window.adminLinksPool) ? window.adminLinksPool : [];
            var _link = _pool.find(function (l) { return l.id === newItem.link_id; });
            newItem.label = (_link && (_link.title || _link.url)) || 'Ссылка';
        } else if (isContent) {
            newItem.type = evt.item.dataset.type;
            if (newItem.type === 'header')    { newItem.tag = 'h2'; newItem.text = 'Новый заголовок'; }
            if (newItem.type === 'paragraph') { newItem.text = 'Новый абзац...'; }
        } else {
            const fieldName = evt.item.dataset.name;
            const source = evt.item.dataset.source;
            const pool = source === 'public' ? allPublicFields : allFields;
            const base = pool.find(f => f.name === fieldName);
            if (!base) return;
            // Не добавляем дубликат
            if (usedNames().has(fieldName)) return;
            Object.assign(newItem, base, {
                required: base.required || false, readonly: false,
                hint: '', withTime: false, fileMultiple: false,
                roles: [], selectMultiple: false, selectSourceType: 'method',
                selectSourceVal: '', selectSaveMethod: '',
            });
        }

        canvasItems.splice(insertIdx, 0, newItem);
        activeId = newItem.id;
        renderAll();
        canvasOpenProps(newItem.id);
    }

    /* ─── Панель свойств ─────────────────────────────────── */
    window.canvasOpenProps = function (id) {
        const item = canvasItems.find(i => i.id === id);
        if (!item) return;
        activeId = id;

        document.getElementById('prop-id').value = id;
        document.getElementById('prop-width').value = item.width || '100';

        const dbGroup      = document.getElementById('props-db-group');
        const contentGroup = document.getElementById('props-content-group');

        if (item.isContent) {
            dbGroup.style.display      = 'none';
            contentGroup.style.display = 'block';

            const isLink = item.type === 'link';
            document.getElementById('props-content-header-opts').style.display  = item.type === 'header'    ? 'block' : 'none';
            document.getElementById('props-content-text-opts').style.display    = (!isLink && item.type !== 'divider') ? 'block' : 'none';
            document.getElementById('props-content-divider-note').style.display = (item.type === 'divider' || isLink) ? 'flex'  : 'none';
            if (item.type === 'header')    document.getElementById('prop-content-tag').value  = item.tag  || 'h2';
            if (!isLink && item.type !== 'divider') document.getElementById('prop-content-text').value = item.text || '';
        } else {
            dbGroup.style.display      = 'block';
            contentGroup.style.display = 'none';

            document.getElementById('prop-db-name').value   = item.name;
            document.getElementById('prop-label').value     = item.label   || '';
            document.getElementById('prop-hint').value      = item.hint    || '';
            document.getElementById('prop-type').value      = item.type    || 'text';
            document.getElementById('prop-required').checked  = !!item.required;
            document.getElementById('prop-readonly').checked  = !!item.readonly;
            document.getElementById('prop-with-time').checked = !!item.withTime;
            document.getElementById('prop-file-multiple').checked  = !!item.fileMultiple;
            document.getElementById('prop-sel-multiple').checked    = !!item.selectMultiple;
            document.getElementById('prop-sel-source-type').value   = item.selectSourceType || 'method';
            document.getElementById('prop-sel-source-val').value    = item.selectSourceVal  || '';
            document.getElementById('prop-sel-save-method').value   = item.selectSaveMethod || '';

            document.querySelectorAll('.role-cb').forEach(cb => {
                cb.checked = Array.isArray(item.roles) && item.roles.includes(cb.value);
            });
            canvasToggleSelectOpts();
        }

        // Подсветка активного элемента
        document.querySelectorAll('.canvas-item').forEach(el => el.classList.remove('is-active'));
        const active = elCanvas.querySelector(`[data-id="${id}"]`);
        if (active) active.classList.add('is-active');

        elPanel.classList.add('is-open');
    };

    window.canvasCloseProps = function () {
        elPanel.classList.remove('is-open');
        activeId = null;
        document.querySelectorAll('.canvas-item').forEach(el => el.classList.remove('is-active'));
        renderCanvas();
    };

    window.canvasUpdateField = function () {
        if (!activeId) return;
        const item = canvasItems.find(i => i.id === activeId);
        if (!item) return;

        item.width = document.getElementById('prop-width').value;

        if (item.isContent) {
            if (item.type === 'header')  item.tag  = document.getElementById('prop-content-tag').value;
            if (item.type !== 'divider') item.text = document.getElementById('prop-content-text').value;
        } else {
            item.label          = document.getElementById('prop-label').value;
            item.hint           = document.getElementById('prop-hint').value;
            item.type           = document.getElementById('prop-type').value;
            item.required       = document.getElementById('prop-required').checked;
            item.readonly       = document.getElementById('prop-readonly').checked;
            item.withTime       = document.getElementById('prop-with-time').checked;
            item.fileMultiple   = document.getElementById('prop-file-multiple').checked;
            item.selectMultiple = document.getElementById('prop-sel-multiple').checked;
            item.selectSourceType  = document.getElementById('prop-sel-source-type').value;
            item.selectSourceVal   = document.getElementById('prop-sel-source-val').value;
            item.selectSaveMethod  = document.getElementById('prop-sel-save-method').value;
            item.roles = Array.from(document.querySelectorAll('.role-cb:checked')).map(cb => cb.value);
        }

        renderCanvas();
        syncHidden();

        // Восстанавливаем подсветку
        const active = elCanvas.querySelector(`[data-id="${activeId}"]`);
        if (active) active.classList.add('is-active');
    };

    window.canvasRemove = function (id) {
        canvasItems = canvasItems.filter(i => i.id !== id);
        if (activeId === id) canvasCloseProps();
        renderAll();
    };

    window.canvasClear = function () {
        if (!confirm('Очистить холст? Все настройки полей будут сброшены.')) return;
        canvasItems = [];
        canvasCloseProps();
        renderAll();
    };

    window.canvasToggleSelectOpts = function () {
        const type     = document.getElementById('prop-type');
        const selGrp   = document.getElementById('props-select-group');
        const dateGrp  = document.getElementById('props-date-group');
        if (type && selGrp) {
            selGrp.style.display = type.value === 'select' ? 'block' : 'none';
        }
        if (type && dateGrp) {
            dateGrp.style.display = type.value === 'date' ? 'block' : 'none';
        }
        var fileGrp = document.getElementById('props-file-group');
        if (type && fileGrp) {
            fileGrp.style.display = type.value === 'file' ? 'block' : 'none';
        }
    };

    /* ─── Запуск ──────────────────────────────────────────── */
    buildRolesGrid();
    buildSelectSources();
    renderAll();
    initSortable();

    // При изменении пула ссылок — перерисовать палитру и холст
    document.addEventListener('admin-links:changed', function () {
        renderLinkPalette();
        renderCanvas();
        syncHidden();
        initSortable();
    });
})();
JS,
    View::POS_END
) ?>
