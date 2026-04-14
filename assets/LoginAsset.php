<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Ассет для страницы авторизации.
 *
 * Подключает скомпилированные Tailwind CSS утилиты, кастомные стили
 * и JS-логику многошаговой формы входа.
 */
class LoginAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $css = [
        'css/login.min.css',
    ];

    public $js = [
        'js/login.min.js',
    ];

    public $jsOptions = [
        'position' => \yii\web\View::POS_END,
    ];
}
