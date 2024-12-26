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

    public function run()
    {
        return $this->render('number');
    }
}