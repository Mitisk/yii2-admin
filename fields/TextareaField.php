<?php
namespace Mitisk\Yii2Admin\fields;

class TextareaField extends Field
{
    /** @var int Максимальная длина поля */
    public $maxlength;

    /** @var string Подтип поля [textarea, visual, html] */
    public $viewtype;

    /** @var int Количество строк */
    public $rows;

    /** @var boolean Только для чтения */
    public $readonly;

    public function renderField()
    {
        return $this->render('textarea', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }
}
