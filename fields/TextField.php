<?php
namespace Mitisk\Yii2Admin\fields;

class TextField extends Field
{
    /** @var int Максимальная длина поля */
    public $maxlength;

    /** @var string Подтип поля */
    public $subtype;

    /** @var boolean Только для чтения */
    public $readonly;

    public function renderField()
    {
        return $this->render('text', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}
