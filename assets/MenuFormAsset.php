<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Редактор меню (/admin/menu/). Исходники скрипта и стилей — assets/src/admin/menu-editor.*
 */
class MenuFormAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'node_modules/sortablejs/Sortable.min.js',
        'js/menu-editor.min.js',
    ];

    public $css = [
        'css/menu-editor.min.css',
    ];

    public $depends = [
        AppAsset::class,
    ];
}
