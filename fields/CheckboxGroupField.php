<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\ArrayHelper;

class CheckboxGroupField extends Field
{
    /** @var boolean Toggle */
    public $toggle;

    /** @var boolean Inline */
    public $inline;

    /** @var boolean Other */
    public $other;

    /** @var array Values [label, value, selected] */
    public $values;

    /** @var boolean Только для чтения */
    public $readonly;

    /** @var string Публичный статический метод, который возвращает массив значений */
    public $publicStaticMethod;

    public function renderField()
    {
        $values = FieldsHelper::getValues($this);

        if (!$values || $values && count($values) == 1) {
            return $this->render('checkbox', [
                'field' => $this,
                'model' => $this->model,
                'fieldId' => $this->fieldId
            ]);
        }

        return $this->render('checkbox-group', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'values' => $values
        ]);
    }
}