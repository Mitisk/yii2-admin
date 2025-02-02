<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class HrLineField extends Field
{
    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return Html::tag('hr');
    }

    public function renderView(): string
    {
        return '';
    }
}
