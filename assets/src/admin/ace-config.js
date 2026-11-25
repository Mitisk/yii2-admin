/*
 * Настройка редактора кода Ace (AceAsset): в пакете лежат только минифицированные режимы и темы
 * (mode-html.min.js, theme-chrome.min.js), а по умолчанию Ace просит mode-html.js — подсветки не было.
 * Исходник; в пакет попадает js/ace-config.min.js (sh assets/src/build.sh).
 */
if (window.ace && window.ace.config) {
    window.ace.config.set('suffix', '.min.js');
}
