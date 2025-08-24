<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Asset для формы редактирования пользователя
 */
class UserFormAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'component/fileuploader/dist/jquery.fileuploader.min.js',
        'component/fileuploader/js/avatar.min.js',
        'js/custom.user.form.min.js',
    ];

    public $css = [
        'component/fileuploader/dist/jquery.fileuploader.min.css',
        'component/fileuploader/dist/font/font-fileuploader.min.css',
        'component/fileuploader/css/jquery.fileuploader-theme-avatar.min.css',
        'css/custom.user.form.min.css',
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        'yii\web\YiiAsset',
        AppAsset::class,
    ];
}