<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\ArrayHelper;

class SelectField extends Field
{
    /** @var boolean Allow Multiple Selections */
    public $multiple;

    /** @var array Values [label, value, selected] */
    public $values;

    /** @var boolean Только для чтения */
    public $readonly;

    /** @var string Публичный статический метод, который возвращает массив значений */
    public $publicStaticMethod;

    public function renderField()
    {
        return $this->render('select', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'values' => FieldsHelper::getValues($this)
        ]);
    }

}
