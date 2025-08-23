<?php
namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Визуальный редактор Trumbowyg
 * @see https://alex-d.github.io/Trumbowyg/
 */
class TrumbowygAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets/node_modules/trumbowyg/dist';

    public $css = [
        'ui/trumbowyg.min.css',
        'plugins/emoji/ui/trumbowyg.emoji.min.css',
        'plugins/table/ui/trumbowyg.table.min.css'
    ];

    public $js = [
        'trumbowyg.min.js',
        'langs/ru.min.js',
        'plugins/emoji/trumbowyg.emoji.min.js',
        'plugins/table/trumbowyg.table.min.js'
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
    ];
}