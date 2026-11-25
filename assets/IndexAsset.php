<?php

namespace Mitisk\Yii2Admin\assets;


class IndexAsset extends PackageAsset
{
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
