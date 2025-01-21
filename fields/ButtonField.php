<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\helpers\Html;

class ButtonField extends Field
{
    /** @var string Стиль */
    public $style;

    /** @var string subtype [button, submit, reset] */
    public $subtype;

    public function renderField()
    {
        return Html::tag($this->subtype, $this->label, ['class' => $this->className]);
    }
}