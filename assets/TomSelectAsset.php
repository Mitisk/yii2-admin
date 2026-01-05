<?php
namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Инпуты TomSelect
 * @see https://tom-select.js.org/
 */
class TomSelectAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets/node_modules/tom-select/dist';

    public $css = [
        'css/tom-select.min.css'
    ];

    public $js = [
        'js/tom-select.complete.min.js',
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
    ];
}