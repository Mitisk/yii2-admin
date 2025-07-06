<?php

namespace Mitisk\Yii2Admin\components;

use yii\base\Component;
use Mitisk\Yii2Admin\models\Settings;

class SettingsComponent extends Component
{
    public function set($modelName, $key, $value, $type = 'string')
    {
        return Settings::setValue($modelName, $key, $value, $type);
    }

    public function get($modelName, $key, $default = null)
    {
        return Settings::getValue($modelName, $key, $default);
    }
}