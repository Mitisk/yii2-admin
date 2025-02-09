<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class NumberField extends Field
{
    /** @var int Минимальное значение */
    public $min;

    /** @var int Максимальное значение */
    public $max;

    /** @var int Шаг */
    public $step;

    /** @var string Подтип поля */
    public $subtype;

    /** @var boolean Только для чтения */
    public $readonly;

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('number', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $value = Html::getAttributeValue($this->model->getModel(), $this->name) ?: '-';
        return $this->model->component->non_encode ? $value : Html::encode($value);
    }
}
