<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\helpers\Html;

class HeaderField extends Field
{
    /** @var int Тип [h1, h2, h3, h4, h5, h6] */
    public $subtype;

    public function renderField()
    {
        return Html::tag($this->subtype, $this->label, ['class' => $this->className]);
    }
}