<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Asset для формы редактирования компонентов
 */
class ComponentFormAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js',
        'component/form-builder/form-builder.min.js',
        'component/form-builder/form-render.min.js',
        'component/form-builder/component-builder.min.js',
        'component/drag-arrange/drag-arrange.min.js'
    ];

    public $css = [
        'component/form-builder/custom-theme.min.css'
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        'yii\web\YiiAsset',
        AppAsset::class
    ];
}
