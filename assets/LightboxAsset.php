<?php
namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

class LightboxAsset extends AssetBundle
{
    // Публикуем файлы из node_modules (npm i tom-select)
    public $sourcePath = '@Mitisk/Yii2Admin/assets/node_modules/lightbox2/dist';

    public $css = [
        'css/lightbox.min.css'
    ];

    public $js = [
        'js/lightbox.min.js', // complete — с плагинами
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
    ];
}