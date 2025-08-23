<?php
namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Увеличение картинок
 * @see https://lokeshdhakar.com/projects/lightbox2/
 */
class LightboxAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets/node_modules/lightbox2/dist';

    public $css = [
        'css/lightbox.min.css'
    ];

    public $js = [
        'js/lightbox.min.js',
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
    ];
}