#!/usr/bin/env sh
# Сборка минифицированных ассетов из assets/src (нужен node + npx).
# Панель на сайте собирается в свою папку assets/admin-bar (AdminBarAsset::$sourcePath).
set -e
cd "$(dirname "$0")/.."
npx --yes esbuild src/admin-bar/bar.js    --minify --target=es2017 --legal-comments=none --outfile=admin-bar/js/admin-bar.min.js
npx --yes esbuild src/admin-bar/loader.js --minify --target=es2017 --legal-comments=none --outfile=admin-bar/js/admin-bar-loader.min.js
npx --yes esbuild src/admin-bar/blocks.js --minify --target=es2017 --legal-comments=none --outfile=admin-bar/js/admin-bar-blocks.min.js
npx --yes esbuild src/admin-bar/bar.css   --minify --outfile=admin-bar/css/admin-bar.min.css
# Умолчания для обычной разметки в рабочей области админки (AppAsset)
npx --yes esbuild src/admin/base.css      --minify --outfile=css/base.min.css
# Вход в админку: шаги логин → пароль → код (LoginAsset)
npx --yes esbuild src/admin/login.js      --minify --target=es2017 --legal-comments=none --outfile=js/login.min.js
npx --yes esbuild src/admin/login.css     --minify --outfile=css/login-steps.min.css
# Форма пользователя: способ входа и подключение приложения (UserFormAsset). QR-код рисует
# библиотека из src/lib (MIT), она встраивается в сборку
npx --yes esbuild src/admin/user-form.js  --bundle --minify --target=es2017 --legal-comments=none --banner:js="/*! QR Code Generator for JavaScript (c) 2009 Kazuhiko Arase, MIT license. 'QR Code' is a registered trademark of DENSO WAVE INCORPORATED */" --outfile=js/custom.user.form.min.js
npx --yes esbuild src/admin/user-form.css --minify --outfile=css/user-form.min.css
# Главная админки: виджеты, заметки, очистка кэша (IndexAsset)
npx --yes esbuild src/admin/dashboard.js  --minify --target=es2017 --legal-comments=none --outfile=js/page/index.min.js
npx --yes esbuild src/admin/dashboard.css --minify --outfile=css/dashboard.min.css
# Редактор меню (MenuFormAsset)
npx --yes esbuild src/admin/menu-editor.js  --minify --target=es2017 --legal-comments=none --outfile=js/menu-editor.min.js
npx --yes esbuild src/admin/menu-editor.css --minify --outfile=css/menu-editor.min.css
# Журналы: действия и ошибки сайта (LogAsset)
npx --yes esbuild src/admin/log.js  --minify --target=es2017 --legal-comments=none --outfile=js/log.min.js
npx --yes esbuild src/admin/log.css --minify --outfile=css/log.min.css
# Редактор шаблона письма (EmailTemplateAsset)
npx --yes esbuild src/admin/email-template.js  --minify --target=es2017 --legal-comments=none --outfile=js/email-template.min.js
npx --yes esbuild src/admin/email-template.css --minify --outfile=css/email-template.min.css
# Оболочка админки: логотип, меню, шапка (AppAsset)
npx --yes esbuild src/admin/shell.css     --minify --outfile=css/shell.min.css
# Редактор кода Ace: режимы и темы в пакете только .min.js (AceAsset)
npx --yes esbuild src/admin/ace-config.js --minify --target=es2017 --legal-comments=none --outfile=js/ace-config.min.js
ls -la admin-bar/js/admin-bar*.js admin-bar/css/admin-bar.min.css css/base.min.css js/login.min.js css/login-steps.min.css js/custom.user.form.min.js css/user-form.min.css js/page/index.min.js css/dashboard.min.css js/menu-editor.min.js css/menu-editor.min.css css/shell.min.css js/log.min.js css/log.min.css js/email-template.min.js css/email-template.min.css js/ace-config.min.js
