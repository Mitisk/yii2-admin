<?php

namespace Mitisk\Yii2Admin\assets;

/**
 * Редактор шаблона письма (/admin/email-template/update/). Исходники — assets/src/admin/email-template.*
 */
class EmailTemplateAsset extends PackageAsset
{
    public $js = [
        'js/email-template.min.js',
    ];

    public $css = [
        'css/email-template.min.css',
    ];

    public $depends = [
        AppAsset::class,
        TrumbowygAsset::class,
    ];
}
