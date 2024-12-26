<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

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

    public function run()
    {
        return $this->render('date');
    }
}