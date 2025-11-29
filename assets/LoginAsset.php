<?php

namespace Mitisk\Yii2Admin\assets;

use yii\web\AssetBundle;

/**
 * Class ComponentAsset
 * @see https://github.com/HichemTab-tech/OTP-designer-jquery
 */
class LoginAsset extends AssetBundle
{
    public $sourcePath = '@Mitisk/Yii2Admin/assets';

    public $js = [
        'js/otpdesigner.min.js',
    ];

    public $depends = [
        'yii\web\JqueryAsset'
    ];
}