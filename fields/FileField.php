<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class FileField extends Field
{
    /** @var boolean Мультизагрузка */
    public $multiple;

    public function run()
    {
        return $this->render('file');
    }
}