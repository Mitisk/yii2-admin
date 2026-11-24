<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Ассеты панели администратора на сайте. CSS подключается самим скриптом
 * внутрь Shadow DOM, поэтому здесь только JS.
 *
 * Своя папка (assets/admin-bar), а не общая папка ассетов админки: каталог публикации
 * зависит только от исходного пути, и при общей папке страница сайта, открытая раньше
 * админки, публиковала туда только файлы панели — /admin/ оставалась без CSS и JS.
 * Сборка из assets/src/admin-bar — sh assets/src/build.sh.
 */
class AdminBarAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets/admin-bar';

    public $js = [
        'js/admin-bar.min.js',
    ];

    public $jsOptions = [
        'position' => View::POS_END,
        'defer' => true,
    ];
}
