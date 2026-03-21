<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class HeaderField extends Field
{
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
