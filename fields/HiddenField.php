<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\helpers\Html;

class HiddenField extends Field
{
    public function renderField()
    {
        return Html::activeHiddenInput($this->model->getModel(), $this->name, ['value' => $this->value]);
    }
}