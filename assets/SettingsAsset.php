<?php

namespace Mitisk\Yii2Admin\assets;


class SettingsAsset extends PackageAsset
{
    public $js = [
        'js/moment.min.js',
        'js/moment-timezone.min.js',
        'js/page/settings.min.js',
    ];
    public $depends = [
        \yii\web\JqueryAsset::class,
    ];
}