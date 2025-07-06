<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

class SettingsAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';
    public $js = [
        'js/moment.min.js',
        'js/moment-timezone.min.js',
        'js/page/settings.min.js',
    ];
    public $depends = [
        \yii\web\JqueryAsset::class,
    ];
}