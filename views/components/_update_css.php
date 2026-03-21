<?php
/* @var $this yii\web\View */

$this->registerCss(<<<CSS
/* ─── Welcome Card (шаг 1) ─────────────────────────────── */
.comp-welcome-outer {
    display: flex; align-items: center; justify-content: center;
    padding: 40px 20px; min-height: calc(100vh - 160px);
}
.comp-welcome-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 10px 24px rgba(0,0,0,.08);
    border: 1px solid #e2e8f0; width: 100%; max-width: 660px; overflow: hidden;
}
.comp-welcome-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #fff; padding: 36px 40px 28px; text-align: center;
}
.comp-welcome-header .icon-wrapper {
    width: 60px; height: 60px; background: rgba(255,255,255,.12); border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; margin: 0 auto 18px;
}
.comp-welcome-body { padding: 36px 40px; }

/* Степпер */
.comp-stepper {
    display: flex; justify-content: center; align-items: center; margin-bottom: 28px;
}
.comp-step {
    display: flex; align-items: center; gap: 9px;
    color: rgba(255,255,255,.45); font-size: 14px; font-weight: 500;
}
.comp-step.active { color: #fff; }
.comp-step-num {
    width: 26px; height: 26px; border-radius: 50%;
    background: rgba(255,255,255,.18); display: flex; align-items: center;
    justify-content: center; font-size: 13px;
}
.comp-step.active .comp-step-num {
    background: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,.3);
}
.comp-step-divider {
    height: 2px; width: 38px; background: rgba(255,255,255,.2); margin: 0 14px;
}

/* ─── Шапка страницы (шаг 2) ───────────────────────────── */
.comp-page-header {
    display: flex; flex-direction: row !important; align-items: center;
    justify-content: space-between; padding: 14px 20px !important;
}
.comp-page-header h4 { margin: 0; font-size: 17px; font-weight: 600; }

/* ─── Секции в вкладках ─────────────────────────────────── */
.comp-tab-content .wg-box h4 {
    font-size: 16px; font-weight: 700; margin: 0;
    padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b;
}

/* ─── Вкладки ──────────────────────────────────────────── */
.comp-nav-tabs {
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 0; flex-wrap: nowrap; overflow-x: auto;
}
.comp-nav-tabs .nav-link {
    border: none; color: #64748b; font-weight: 500; font-size: 14px;
    padding: 11px 18px; white-space: nowrap; position: relative;
}
.comp-nav-tabs .nav-link:hover { color: #3b82f6; }
.comp-nav-tabs .nav-link.active { color: #3b82f6; background: transparent; }
.comp-nav-tabs .nav-link.active::after {
    content: ''; position: absolute; bottom: -2px; left: 0; right: 0;
    height: 2px; background: #3b82f6; border-radius: 2px 2px 0 0;
}
.comp-tab-content { margin-top: 0; }
.comp-tab-content .tab-pane { padding-top: 20px; }

/* ─── Визуальный холст ─────────────────────────────────── */
.canvas-layout {
    display: grid; grid-template-columns: 280px 1fr; gap: 20px; min-height: 480px;
}
@media (max-width: 900px) { .canvas-layout { grid-template-columns: 1fr; } }

.canvas-sidebar {
    background: #f8fafc; border-radius: 10px; padding: 16px;
    border: 1px solid #e2e8f0; height: fit-content; position: sticky; top: 80px;
}
.canvas-fields-list { min-height: 30px; }
.canvas-field-pill {
    background: #fff; border: 1px solid #e2e8f0; padding: 9px 12px;
    border-radius: 8px; margin-bottom: 7px; cursor: grab; display: flex;
    align-items: center; gap: 9px; font-size: 14px; font-weight: 500;
    transition: border-color .15s, box-shadow .15s, transform .15s;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
}
.canvas-field-pill:hover {
    border-color: #3b82f6; transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(59,130,246,.12);
}
.canvas-field-pill .drag-icon { color: #cbd5e1; cursor: grab; flex-shrink: 0; }
.canvas-field-pill--public {
    border-color: #a5f3fc; background: #f0fdff;
}
.canvas-field-pill--public:hover {
    border-color: #06b6d4; box-shadow: 0 3px 8px rgba(6,182,212,.15);
}

.canvas-section-title {
    margin: 16px 0 8px; border-top: 2px dashed #e2e8f0; padding-top: 12px;
    font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .5px;
}

.canvas-drop-area {
    background: #fff; border: 2px dashed #e2e8f0; border-radius: 12px;
    padding: 14px; display: flex; flex-wrap: wrap; gap: 10px;
    align-content: flex-start; min-height: 420px;
    transition: background .25s, border-color .25s; position: relative;
}
.canvas-drop-area.drag-over { background: #eff6ff; border-color: #3b82f6; }
.canvas-empty-state {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    text-align: center; color: #94a3b8; pointer-events: none; width: 100%;
}
.canvas-empty-state i {
    font-size: 40px; color: #cbd5e1; display: block; margin-bottom: 12px;
}

.canvas-item {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 14px; display: flex; align-items: flex-start; gap: 12px;
    cursor: pointer; transition: border-color .15s, box-shadow .15s;
    box-shadow: 0 1px 2px rgba(0,0,0,.04); position: relative; overflow: hidden; margin: 0;
}
.canvas-item::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: transparent; transition: background .15s;
}
.canvas-item:hover, .canvas-item.is-active {
    border-color: #3b82f6; box-shadow: 0 3px 10px rgba(59,130,246,.1);
}
.canvas-item.is-active::before { background: #3b82f6; }
.canvas-item .drag-icon { color: #cbd5e1; cursor: grab; margin-top: 3px; flex-shrink: 0; }
.canvas-item-body { flex-grow: 1; overflow: hidden; }
.canvas-item-label {
    font-weight: 600; margin-bottom: 3px; display: flex;
    align-items: center; gap: 7px; font-size: 14px;
}
.canvas-req-badge {
    font-size: 11px; background: #fee2e2; color: #b91c1c;
    padding: 1px 6px; border-radius: 4px;
}
.canvas-item-meta {
    font-size: 12px; color: #64748b; display: flex; gap: 10px; flex-wrap: wrap; padding-bottom: 6px;
}
.canvas-item-meta code {
    font-size: 11px; padding: 1px 4px; background: #f1f5f9;
    border-radius: 4px; color: #475569; vertical-align: baseline;
}
.canvas-item-remove {
    opacity: 0; transition: opacity .15s; color: #ef4444; background: #fee2e2;
    border: none; width: 30px; height: 30px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0;
}
.canvas-item:hover .canvas-item-remove { opacity: 1; }
.canvas-item-remove:hover { background: #fecaca; }
.canvas-item.content-header .canvas-item-body {
    font-weight: 700; font-size: 18px; color: #0f172a;
}
.canvas-item.content-paragraph .canvas-item-body { color: #64748b; font-style: italic; }
.canvas-item.content-divider .canvas-item-body {
    border-top: 2px dashed #cbd5e1; width: 100%; margin-top: 10px;
}

/* Ширины на холсте */
.cw-100 { width: 100%; }
.cw-50  { width: calc(50% - 5px); }
.cw-33  { width: calc(33.333% - 7px); }
.cw-25  { width: calc(25% - 8px); }

/* ─── Панель свойств ────────────────────────────────────── */
.canvas-props-panel {
    position: fixed; top: 0; right: -440px; width: 440px; height: 100vh;
    background: #fff; box-shadow: -3px 0 14px rgba(0,0,0,.06);
    z-index: 1060; transition: right .28s cubic-bezier(.4,0,.2,1);
    display: flex; flex-direction: column; border-left: 1px solid #e2e8f0;
}
.canvas-props-panel.is-open { right: 0; }
.canvas-props-header {
    padding: 18px 22px; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center;
    flex-shrink: 0;
}
.canvas-props-body { padding: 22px; overflow-y: auto; flex-grow: 1; }
.canvas-props-footer {
    padding: 16px 22px; border-top: 1px solid #e2e8f0;
    background: #f8fafc; flex-shrink: 0;
}
.prop-section {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 8px; padding: 14px; margin-bottom: 18px;
}
.prop-section h6 {
    font-size: 13px; font-weight: 700; margin-bottom: 12px; color: #475569;
}
.canvas-roles-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; }
.canvas-roles-grid label {
    display: flex; align-items: center; gap: 6px; font-size: 14px; cursor: pointer;
}

.sortable-ghost {
    opacity: .35; background: #f1f5f9; border: 2px dashed #94a3b8 !important;
}

/* ─── Форма в панели свойств (изоляция от темы) ─────────── */
.canvas-props-body .form-control,
.canvas-props-body .form-select {
    border: 1px solid #cbd5e1; border-radius: 8px;
    padding: 8px 12px; font-size: 14px; color: #1e293b;
    background: #fff; box-shadow: none;
}
.canvas-props-body .form-control:focus,
.canvas-props-body .form-select:focus {
    border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); outline: none;
}
.canvas-props-body .body-title { color: #475569 !important; font-size: 14px; font-weight: 600; }
.canvas-props-body label:not(.form-check-label) {
    color: #475569; font-size: 14px; font-weight: 600;
}

/* ─── Grid: колонки таблицы ─────────────────────────────── */
.grid-column-list {
    border: 1px solid #e2e8f0; border-radius: 10px;
    background: #fff; overflow: hidden;
}
.grid-column-item {
    padding: 14px 18px; border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: center;
    justify-content: space-between; transition: background .15s;
}
.grid-column-item:last-child { border-bottom: none; }
.grid-column-item:hover { background: #f8fafc; }
.grid-column-item.grid-column-actions { background: #f8fafc; }
.grid-column-drag {
    color: #cbd5e1; cursor: grab; margin-right: 12px; flex-shrink: 0;
}
.grid-actions-config {
    background: #f1f5f9; padding: 4px; border-radius: 8px;
    display: flex; gap: 4px; align-items: center; margin-left: 14px;
}
.action-btn-check { display: none; }
.action-btn-label {
    border: none; color: #94a3b8; background: transparent;
    border-radius: 6px; padding: 5px 11px; font-size: 13px;
    cursor: pointer; transition: all .15s;
}
.action-btn-check:checked + .action-btn-label.view {
    background: #e0f2fe; color: #0284c7;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
}
.action-btn-check:checked + .action-btn-label.update {
    background: #fef3c7; color: #d97706;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
}
.action-btn-check:checked + .action-btn-label.delete {
    background: #fee2e2; color: #dc2626;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
}
CSS
);
