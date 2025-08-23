<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Asset для поля выбора значений
 * @see https://harvesthq.github.io/chosen/
 */
class FieldSelectAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'node_modules/chosen-js/chosen.jquery.min.js',
        'js/custom.chosen.min.js'
    ];

    public $css = [
        'node_modules/chosen-js/chosen.min.css',
        'css/custom.chosen.min.css'
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
        'yii\web\YiiAsset',
        AppAsset::class
    ];
}
