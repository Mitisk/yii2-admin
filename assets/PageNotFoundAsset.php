<?php
namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Страница 404
 */
class PageNotFoundAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $css = [
        'css/404.min.css'
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
    ];
}
