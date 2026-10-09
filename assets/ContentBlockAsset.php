<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Страницы раздела «Контент» → «Текстовые блоки».
 */
class ContentBlockAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'js/page/content-block.min.js',
    ];

    /** Trumbowyg — зависимость, чтобы загрузиться раньше скрипта страницы, который его инициализирует. */
    public $depends = [
        'yii\web\JqueryAsset',
        'yii\web\YiiAsset',
        TrumbowygAsset::class,
    ];
}
