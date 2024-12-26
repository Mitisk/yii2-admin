<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class SelectField extends Field
{
    /** @var boolean Allow Multiple Selections */
    public $multiple;

    /** @var array Values [label, value, selected] */
    public $values;

    public function run()
    {
        return $this->render('select');
    }
}