<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class ParagraphField extends Field
{
    /** @var int Тип [p, address, blockquote, canvas, output] */
    public $subtype;

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return Html::tag($this->subtype, $this->label, ['class' => $this->className]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        return '';
    }
}
