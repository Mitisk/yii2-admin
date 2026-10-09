#!/usr/bin/env sh
# Сборка минифицированных ассетов из assets/src (нужен node + npx).
set -e
cd "$(dirname "$0")/.."
npx --yes esbuild src/admin-bar/bar.js    --minify --target=es2017 --legal-comments=none --outfile=js/admin-bar.min.js
npx --yes esbuild src/admin-bar/loader.js --minify --target=es2017 --legal-comments=none --outfile=js/admin-bar-loader.min.js
npx --yes esbuild src/admin-bar/blocks.js --minify --target=es2017 --legal-comments=none --outfile=js/admin-bar-blocks.min.js
npx --yes esbuild src/admin-bar/bar.css   --minify --outfile=css/admin-bar.min.css
ls -la js/admin-bar*.js css/admin-bar.min.css
