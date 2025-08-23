<?php
namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Редактор кода
 * @see https://ace.c9.io/
 */
class AceAsset extends AssetBundle
{

    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $css = [
    ];

    public $js = [
        'node_modules/ace-builds/src-noconflict/ace.js',
        'js/custom.ace.min.js',
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
    ];
}