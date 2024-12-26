<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class RadioGroupField extends Field
{
    /** @var int Другие */
    public $other;

    /** @var array Values [label, value, selected] */
    public $values;

    public function run()
    {
        return $this->render('radio_group');
    }
}