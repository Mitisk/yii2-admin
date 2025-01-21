<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class NumberField extends Field
{
    /** @var int Минимальное значение */
    public $min;

    /** @var int Максимальное значение */
    public $max;

    /** @var int Шаг */
    public $step;

    public $subtype;

    public function renderField()
    {
        return $this->render('number', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}