<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

class IndexAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $css = [
        'css/dashboard.min.css',
    ];

    public $js = [
        'node_modules/sortablejs/Sortable.min.js',
        'js/page/index.min.js',
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        'yii\web\YiiAsset',
        TrumbowygAsset::class,
        AppAsset::class
    ];
}
