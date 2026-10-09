<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Asset для поля «Изображение с кадрированием» (Cropper.js v1).
 */
class FieldImageAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $css = [
        'node_modules/cropperjs/dist/cropper.min.css',
    ];

    public $js = [
        'node_modules/cropperjs/dist/cropper.min.js',
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        AppAsset::class,
    ];
}
