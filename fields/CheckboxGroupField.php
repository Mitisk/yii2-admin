<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class CheckboxGroupField extends Field
{
    /** @var boolean Toggle */
    public $toggle;

    /** @var boolean Inline */
    public $inline;

    /** @var boolean Other */
    public $other;

    /** @var array Values [label, value, selected] */
    public $values;

    public function run()
    {
        return $this->render('checkbox_group');
    }
}