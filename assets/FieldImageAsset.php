<?php

namespace Mitisk\Yii2Admin\assets;


/**
 * Asset для поля «Изображение с кадрированием» (Cropper.js v1).
 */
class FieldImageAsset extends PackageAsset
{
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
