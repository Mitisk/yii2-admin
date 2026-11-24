#!/usr/bin/env sh
# Сборка минифицированных ассетов из assets/src (нужен node + npx).
# Панель на сайте собирается в свою папку assets/admin-bar (AdminBarAsset::$sourcePath).
set -e
cd "$(dirname "$0")/.."
npx --yes esbuild src/admin-bar/bar.js    --minify --target=es2017 --legal-comments=none --outfile=admin-bar/js/admin-bar.min.js
npx --yes esbuild src/admin-bar/loader.js --minify --target=es2017 --legal-comments=none --outfile=admin-bar/js/admin-bar-loader.min.js
npx --yes esbuild src/admin-bar/blocks.js --minify --target=es2017 --legal-comments=none --outfile=admin-bar/js/admin-bar-blocks.min.js
npx --yes esbuild src/admin-bar/bar.css   --minify --outfile=admin-bar/css/admin-bar.min.css
ls -la admin-bar/js/admin-bar*.js admin-bar/css/admin-bar.min.css
