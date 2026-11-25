<?php
namespace Mitisk\Yii2Admin\assets;


/**
 * Страница 404
 */
class PageNotFoundAsset extends PackageAsset
{
    public $css = [
        'css/404.min.css'
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
    ];
}
