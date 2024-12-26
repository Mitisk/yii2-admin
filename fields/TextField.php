<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class TextField extends Field
{
    /** @var int Максимальная длина поля */
    public $maxlength;

    /** @var string Подтип поля */
    public $subtype;

    public function run()
    {
        return $this->render('text');
    }
}