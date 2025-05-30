<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class HiddenField extends Field
{
    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return Html::activeHiddenInput($this->model->getModel(), $this->name, ['value' => $this->value]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $value = Html::getAttributeValue($this->model->getModel(), $this->name);
        return $this->model->component->non_encode ? $value : Html::encode($value);
    }
}
