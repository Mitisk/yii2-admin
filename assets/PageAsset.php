<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Страницы раздела «Контент» → «Страницы»: редактор (Trumbowyg + Ace для вкладки HTML).
 */
class PageAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'js/page/page.min.js',
    ];

    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
        TrumbowygAsset::class,
        AceAsset::class,
    ];
}
