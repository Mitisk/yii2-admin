<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class FileField extends Field
{
    /** @var boolean Мультизагрузка */
    public $multiple;

    public function renderField()
    {
        return $this->render('file', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}