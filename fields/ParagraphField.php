<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;

class ParagraphField extends Field
{
    /** @var int Тип [p, address, blockquote, canvas, output] */
    public $subtype;

    public function run()
    {
        return $this->render('paragraph');
    }
}