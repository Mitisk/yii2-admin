<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

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

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    public function renderList(string $column): array
    {
        return [
            'attribute' => $column,
            'value' => function ($data) use ($column) {
                return strip_tags($data->{$column});
            }
        ];
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('textarea', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $value = Html::getAttributeValue($this->model->getModel(), $this->name) ?: '-';
        return $this->model->component->non_encode ? $value : Html::encode($value);
    }
}
