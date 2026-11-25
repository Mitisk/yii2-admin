<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Родитель бандлов, которые публикуют общую папку пакета `assets/`.
 *
 * Все они копируются в одну папку web/assets (хеш зависит только от пути), и её содержимое
 * определяет бандл, опубликовавший её первым. Поэтому настройки публикации у всех общие:
 * в web/assets не уходят 26 тысяч SVG-иконок Font Awesome, лишние сборки редактора кода,
 * исходники и PHP-классы бандлов. Без исключений первый запрос копировал 131 МБ / 29 тысяч
 * файлов и на хостинге обрывался по лимиту времени — админка оставалась без стилей.
 *
 * Новый бандл с sourcePath '@Mitisk/Yii2Admin/assets' наследует этот класс
 * (страж — tests/unit/assets/PackageAssetPublishTest.php).
 */
abstract class PackageAsset extends AssetBundle
{
    /** Что не копировать в web/assets (шаблоны как в .gitignore, от корня папки assets) */
    public const PUBLISH_OPTIONS = [
        'except' => [
            '*.php',
            '/src/',
            '/package-lock.json',
            '/component/fa/svgs/',
            '/component/fa/js/',
            '/component/fa/sprites/',
            '/node_modules/ace-builds/src/',
            '/node_modules/ace-builds/src-min/',
            '/node_modules/ace-builds/src-min-noconflict/',
            '/node_modules/ace-builds/types/',
        ],
    ];

    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $publishOptions = self::PUBLISH_OPTIONS;
}
