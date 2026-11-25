<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\assets;


/**
 * Ассет для страницы авторизации.
 *
 * Подключает скомпилированные Tailwind CSS утилиты, кастомные стили
 * и JS-логику многошаговой формы входа.
 */
class LoginAsset extends PackageAsset
{
    public $css = [
        'css/login.min.css',
        'css/login-steps.min.css',
    ];

    public $js = [
        'js/login.min.js',
    ];

    public $jsOptions = [
        'position' => \yii\web\View::POS_END,
    ];
}
