<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Ассеты панели администратора на сайте. CSS подключается самим скриптом
 * внутрь Shadow DOM, поэтому здесь только JS.
 */
class AdminBarAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'js/admin-bar.min.js',
    ];

    public $jsOptions = [
        'position' => View::POS_END,
        'defer' => true,
    ];

    public $publishOptions = [
        'only' => [
            'js/admin-bar.min.js',
            'js/admin-bar-loader.min.js',
            'css/admin-bar.min.css',
        ],
    ];
}
