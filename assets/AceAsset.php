<?php
namespace Mitisk\Yii2Admin\assets;


/**
 * Редактор кода
 * @see https://ace.c9.io/
 */
class AceAsset extends PackageAsset
{
    public $css = [
    ];

    public $js = [
        'node_modules/ace-builds/src-noconflict/ace.min.js',
        'js/ace-config.min.js',
        'js/custom.ace.min.js',
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
    ];
}