<?php
namespace Mitisk\Yii2Admin\fields;

class FileField extends Field
{
    /** @var boolean Мультизагрузка */
    public $multiple;

    /** @var boolean Только для чтения */
    public $readonly;

    public function renderField()
    {
        return $this->render('file', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}