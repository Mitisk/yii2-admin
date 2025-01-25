<?php
namespace Mitisk\Yii2Admin\fields;

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

    public function renderField()
    {
        return $this->render('number', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}