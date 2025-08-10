<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

class AppAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $css = [
        'css/animate.min.css',
        'css/animation.min.css',
        'css/bootstrap.min.css',
        'css/bootstrap-select.min.css',
        'css/style.min.css',
        'font/fonts.min.css',
        'icon/style.min.css',
        'css/site.min.css',
    ];

    public $js = [
        'js/bootstrap.min.js',
        'js/bootstrap-select.min.js',
        'js/main.min.js'
    ];

    public $depends = [
        'yii\web\YiiAsset',
        TomSelectAsset::class,
        LightboxAsset::class,
    ];
}