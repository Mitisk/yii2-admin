<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\helpers\Html;

class TextField extends Field
{
    /** @var int Максимальная длина поля */
    public $maxlength;

    /** @var string Подтип поля */
    public $subtype;

    public function renderField()
    {
        return $this->render('text', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}