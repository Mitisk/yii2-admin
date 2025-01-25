<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class HrLineField extends Field
{
    public function renderField()
    {
        return Html::tag('hr');
    }
}
