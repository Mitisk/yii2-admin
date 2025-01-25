<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class ParagraphField extends Field
{
    /** @var int Тип [p, address, blockquote, canvas, output] */
    public $subtype;

    public function renderField()
    {
        return Html::tag($this->subtype, $this->label, ['class' => $this->className]);
    }
}
