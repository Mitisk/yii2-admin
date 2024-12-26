<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class TextareaField extends Field
{
    /** @var int Максимальная длина поля */
    public $maxlength;

    /** @var string Подтип поля [textarea, tinymce, quill] */
    public $subtype;

    /** @var int Количество строк */
    public $rows;

    public function run()
    {
        return $this->render('textarea');
    }
}