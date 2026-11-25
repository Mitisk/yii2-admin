<?php

namespace Mitisk\Yii2Admin\assets;

class AppAsset extends PackageAsset
{
    public $css = [
        'css/animate.min.css',
        'css/animation.min.css',
        'css/bootstrap.min.css',
        'css/bootstrap-select.min.css',
        'css/style.min.css',
        'font/fonts.min.css',
        'js/bootstrap-iconpicker/css/bootstrap-iconpicker.min.css',
        'component/fa/css/all.min.css',
        'icon/style.min.css',
        'css/site.min.css',
        'css/admin-links.min.css',
        // Последним: умолчания для обычной разметки (голые теги, .table, .btn, LinkPager) — src/admin/base.css
        'css/shell.min.css',
        'css/base.min.css',
    ];

    public $js = [
        'js/bootstrap.min.js',
        'js/bootstrap-select.min.js',
        'js/main.min.js',
        'js/grid-selection.min.js',
        'js/admin-link-ajax.min.js',
    ];

    public $depends = [
        'yii\web\YiiAsset',
        TomSelectAsset::class,
        LightboxAsset::class,
    ];
}