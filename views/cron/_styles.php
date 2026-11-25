<?php
/* @var $this yii\web\View */

// Стили раздела «Планировщик». Размеры в px: корневой шрифт темы — 10px.
$this->registerCss(<<<'CSS'
.cr [hidden] { display: none !important; }
.cr-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.cr-top .tf-button { gap: 8px; height: 44px; padding: 0 20px; font-size: 14px; }
.cr-state { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-radius: 14px; background: var(--White, #fff); box-shadow: 0 4px 24px 2px rgba(20, 25, 38, .05); }
.cr-state__dot { flex: 0 0 12px; width: 12px; height: 12px; border-radius: 50%; background: #94a3b8; }
.cr-state--ok .cr-state__dot { background: #22c55e; box-shadow: 0 0 0 4px rgba(34, 197, 94, .18); }
.cr-state--off .cr-state__dot { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, .2); }
.cr-state__title { font-size: 14px; font-weight: 700; line-height: 20px; color: var(--Heading, #111); }
.cr-state__text { font-size: 13px; line-height: 19px; color: var(--Body-Text, #575864); }
.cr-card { padding: 20px 24px; border-radius: 14px; background: var(--White, #fff); box-shadow: 0 4px 24px 2px rgba(20, 25, 38, .05); }
.cr-card__title { margin: 0 0 6px; font-size: 17px; font-weight: 700; line-height: 24px; color: var(--Heading, #111); }
.cr-text { margin: 0 0 12px; font-size: 14px; line-height: 21px; color: var(--Body-Text, #575864); }
.cr-text--muted { margin: 12px 0 0; font-size: 13px; }
.cr-text code, .cr-hint code { padding: 1px 6px; border-radius: 6px; background: var(--bg-table-1, #f1f5f9); font-size: 12.5px; }
.cr-setup { margin-bottom: 20px; border: 1px solid #fde68a; }
.cr-code { margin: 0; padding: 14px 16px; border-radius: 10px; background: #0f172a; color: #e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; line-height: 20px; white-space: pre-wrap; word-break: break-all; user-select: all; }
.cr-empty { text-align: center; padding: 40px 24px; }
.cr-empty .cr-text { max-width: 560px; margin: 0 auto 16px; }
.cr-list { display: grid; gap: 12px; }
.cr-task { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) auto; gap: 16px 24px; align-items: center; }
.cr-task.is-off { opacity: .72; }
.cr-task__name { display: block; font-size: 16px; font-weight: 700; line-height: 22px; color: var(--Heading, #111); overflow-wrap: anywhere; }
.cr-task__name:hover { color: var(--Main, #2275fc); }
.cr-task__command { display: inline-block; max-width: 100%; margin-top: 4px; padding: 2px 8px; border-radius: 6px; background: var(--bg-table-1, #f1f5f9); color: var(--Heading, #111); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; line-height: 18px; overflow-wrap: anywhere; }
.cr-task__schedule { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 6px; font-size: 13px; line-height: 19px; color: var(--Heading, #111); }
.cr-muted { color: var(--Body-Text, #575864); font-size: 12.5px; }
.cr-task__facts { display: grid; gap: 8px; }
.cr-fact { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; font-size: 13px; line-height: 19px; }
.cr-fact__label { flex: 0 0 130px; color: var(--Body-Text, #575864); }
.cr-fact__value { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 6px; color: var(--Heading, #111); font-weight: 600; }
.cr-chip { padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 18px; white-space: nowrap; }
.cr-chip--ok { background: #dcfce7; color: #15803d; }
.cr-chip--fail { background: #fee2e2; color: #b91c1c; }
.cr-chip--run { background: #dbeafe; color: #1d4ed8; }
.cr-chip--code { margin-left: 8px; background: rgba(34, 117, 252, .1); color: #1d4ed8; vertical-align: middle; }
.cr-errors { margin: 0; padding-left: 18px; font-size: 13px; line-height: 20px; color: #b45309; }
.cr-readonly { display: grid; grid-template-columns: 170px minmax(0, 1fr); gap: 10px 16px; margin: 0 0 18px; }
.cr-readonly dt { font-size: 13px; font-weight: 700; line-height: 20px; color: var(--Heading, #111); }
.cr-readonly dd { margin: 0; font-size: 14px; line-height: 20px; color: var(--Heading, #111); overflow-wrap: anywhere; }
.cr-readonly code { padding: 1px 6px; border-radius: 6px; background: var(--bg-table-1, #f1f5f9); font-size: 12.5px; }
@media (max-width: 575px) { .cr-readonly { grid-template-columns: 1fr; gap: 2px 0; } .cr-readonly dd { margin-bottom: 8px; } }
.cr-chip--manual { background: var(--bg-table-1, #f1f5f9); color: var(--Body-Text, #575864); }
.cr-task__tools { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
.cr-task__tools form { margin: 0; }
.cr-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border: 1px solid var(--Stroke, #e6e9ee); border-radius: 10px; background: var(--White, #fff); color: var(--Heading, #111); font-size: 13px; font-weight: 600; line-height: 18px; white-space: nowrap; cursor: pointer; transition: border-color .15s, color .15s; }
.cr-btn:hover { border-color: var(--Main, #2275fc); color: var(--Main, #2275fc); }
.cr-btn:disabled { opacity: .5; cursor: not-allowed; }
.cr-btn--danger:hover { border-color: #ef4444; color: #b91c1c; }
.cr-form { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; }
.cr-field { display: block; margin: 0 0 18px; }
.cr-field__label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; line-height: 18px; color: var(--Heading, #111); }
.cr .cr-field input[type="text"], .cr .cr-field input[type="number"], .cr .cr-field select { width: 100%; height: 44px; padding: 0 14px; border: 1px solid var(--Input, #e6e9ee); border-radius: 10px; background: var(--White, #fff); color: var(--Heading, #111); font-size: 14px; line-height: 20px; }
.cr .cr-field input:focus, .cr .cr-field select:focus { border-color: var(--Main, #2275fc); outline: 0; box-shadow: 0 0 0 3px rgba(34, 117, 252, .14); }
.cr .cr-field input.cr-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.cr-prefix { display: flex; align-items: stretch; }
.cr-prefix > span { display: flex; align-items: center; padding: 0 12px; border: 1px solid var(--Input, #e6e9ee); border-right: 0; border-radius: 10px 0 0 10px; background: var(--bg-table-1, #f8fafc); color: var(--Body-Text, #575864); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; white-space: nowrap; }
.cr .cr-prefix input[type="text"] { border-radius: 0 10px 10px 0; }
.cr-hint { display: block; margin-top: 6px; font-size: 12.5px; line-height: 18px; color: var(--Body-Text, #575864); }
.cr-error { display: block; margin-top: 6px; color: var(--Palette-Red-500, #ef4444); font-size: 13px; line-height: 18px; }
.cr-error:empty { display: none; }
.cr-schedule-read { margin-top: 8px; padding: 10px 14px; border-radius: 10px; background: rgba(34, 117, 252, .07); color: var(--Heading, #111); font-size: 13px; line-height: 19px; }
.cr-check { display: flex; align-items: center; gap: 10px; margin: 0 0 18px; font-size: 14px; line-height: 20px; color: var(--Heading, #111); cursor: pointer; }
.cr-check input[type="checkbox"] { position: static; width: 18px; height: 18px; margin: 0; opacity: 1; appearance: auto; -webkit-appearance: checkbox; accent-color: var(--Main, #2275fc); }
.cr-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 20px; margin-top: 4px; }
.cr-actions .tf-button { height: 44px; padding: 0 24px; font-size: 14px; }
.cr-link { color: var(--Body-Text, #575864); font-size: 14px; font-weight: 600; }
.cr-link:hover { color: var(--Main, #2275fc); }
.cr-side { display: grid; gap: 16px; }
.cr-side .cr-card { padding: 18px 20px; }
.cr-side form { margin: 0; }
.cr-side .cr-btn { width: 100%; justify-content: center; }
.cr-log { margin-top: 24px; }
.cr-log__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.cr-log__head form { margin: 0; }
.cr-run { border-top: 1px solid var(--Stroke, #e6e9ee); }
.cr-run > summary { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; padding: 12px 0; font-size: 13px; line-height: 19px; color: var(--Heading, #111); cursor: pointer; list-style: none; }
.cr-run > summary::-webkit-details-marker { display: none; }
.cr-run__time { flex: 0 0 150px; font-weight: 600; font-variant-numeric: tabular-nums; }
.cr-run__output { margin: 0 0 14px; padding: 12px 14px; border-radius: 10px; background: #0f172a; color: #e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; line-height: 19px; white-space: pre-wrap; overflow-wrap: anywhere; max-height: 360px; overflow: auto; }
@media (max-width: 1199px) { .cr-task { grid-template-columns: 1fr; } .cr-task__tools { justify-content: flex-start; } .cr-form { grid-template-columns: 1fr; } }
@media (max-width: 575px) { .cr-card { padding: 16px; } .cr-fact__label { flex-basis: 100%; } }
CSS);
