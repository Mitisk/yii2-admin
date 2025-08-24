<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Asset для формы редактирования меню
 */
class MenuFormAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js',
        'component/menu-editor/jquery-menu-editor.min.js',
        'component/bootstrap-iconpicker/js/iconset/fontawesome5-3-1.min.js',
        'component/bootstrap-iconpicker/js/bootstrap-iconpicker.min.js',
        'js/custom.menu.form.min.js'
    ];

    public $css = [
        'css/custom.menu.form.min.css'
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        'yii\web\YiiAsset',
        AppAsset::class,
    ];
}