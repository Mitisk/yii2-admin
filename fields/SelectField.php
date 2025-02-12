<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;

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

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    public function renderList(string $column): array
    {
        $values = FieldsHelper::getValues($this);

        return [
            'attribute' => $column,
            'value' => function ($data) use ($values, $column) {
                return ArrayHelper::getValue($values, $data->{$column});
            }
        ];
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('select', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'values' => FieldsHelper::getValues($this)
        ]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $values = FieldsHelper::getValues($this);
        $value = Html::getAttributeValue($this->model->getModel(), $this->name);
        return ArrayHelper::getValue($values, $value, '-');
    }

}
