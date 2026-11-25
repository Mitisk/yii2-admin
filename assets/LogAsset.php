<?php

namespace Mitisk\Yii2Admin\assets;

/**
 * Журналы (/admin/log/). Исходники скрипта и стилей — assets/src/admin/log.*
 */
class LogAsset extends PackageAsset
{
    public $js = [
        'js/log.min.js',
    ];

    public $css = [
        'css/log.min.css',
    ];

    public $depends = [
        AppAsset::class,
    ];
}
