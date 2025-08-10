<?php
namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

class TomSelectAsset extends AssetBundle
{
    // Публикуем файлы из node_modules (npm i tom-select)
    public $sourcePath = '@Mitisk/Yii2Admin/assets/node_modules/tom-select/dist';

    public $css = [
        'css/tom-select.css'
    ];

    public $js = [
        'js/tom-select.complete.min.js', // complete — с плагинами
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
    ];
}