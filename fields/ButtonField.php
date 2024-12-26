<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class ButtonField extends Field
{
    /** @var string Стиль */
    public $style;

    /** @var string subtype [button, submit, reset] */
    public $subtype;

    public function run()
    {
        return $this->render('button');
    }
}