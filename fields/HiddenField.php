<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class HiddenField extends Field
{
    public function run()
    {
        return $this->render('hidden');
    }
}