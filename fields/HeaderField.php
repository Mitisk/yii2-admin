<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class HeaderField extends Field
{
    /** @var int Тип [h1, h2, h3, h4, h5, h6] */
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
