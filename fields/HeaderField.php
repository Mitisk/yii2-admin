<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class HeaderField extends Field
{
    /** @var int Тип [h1, h2, h3, h4, h5, h6] */
    public $subtype;

    public function run()
    {
        return $this->render('header');
    }
}