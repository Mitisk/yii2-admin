<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class AutocompleteField extends Field
{
    /** @var boolean requireValidOption */
    public $requireValidOption;

    /** @var array Values [label, value, selected] */
    public $values;

    public function run()
    {
        return $this->render('autocomplete');
    }
}