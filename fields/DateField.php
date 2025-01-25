<?php
namespace Mitisk\Yii2Admin\fields;

class DateField extends Field
{
    /** @var string Минимальное значение */
    public $min;

    /** @var string Максимальное значение */
    public $max;

    /** @var string Шаг даты */
    public $step;

    /** @var string Подтип поля */
    public $subtype;

    /** @var boolean Только для чтения */
    public $readonly;

    public function renderField()
    {
        return $this->render('date', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}