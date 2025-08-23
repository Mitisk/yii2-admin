<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Asset для поля загрузки файлов
 */
class FieldFileAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'component/fileuploader/dist/jquery.fileuploader.min.js',
        'component/fileuploader/js/custom.min.js',
    ];

    public $css = [
        'component/fileuploader/dist/jquery.fileuploader.min.css',
        'component/fileuploader/dist/font/font-fileuploader.min.css',
        'component/fileuploader/css/jquery.fileuploader-theme-thumbnails.min.css',
        'component/fileuploader/css/custom.file.field.min.css',
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        'yii\web\YiiAsset',
        AppAsset::class,
    ];
}
