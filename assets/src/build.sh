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
ls -la admin-bar/js/admin-bar*.js admin-bar/css/admin-bar.min.css css/base.min.css js/login.min.js css/login-steps.min.css js/custom.user.form.min.js css/user-form.min.css js/page/index.min.js css/dashboard.min.css
