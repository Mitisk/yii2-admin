<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class DateField extends Field
{
    /** @var string Минимальное значение */
    public $min;

    /** @var string Максимальное значение */
    public $max;

    /** @var string Шаг даты */
    public $step;

    /** @var string Подтип поля */
    public $subtype;

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
                $date = $data->{$column};

                if($date) {
                    $date = \Yii::$app->formatter->asDate($date, 'php:d.m.Y H:i:s');
                }

                return $date ?: '-';
            }
        ];
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('date', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $date = Html::getAttributeValue($this->model->getModel(), $this->name);

        if($date) {
            $date = \Yii::$app->formatter->asDate($date, 'php:d.m.Y H:i:s');
        }

        return $date ?: '-';
    }
}
