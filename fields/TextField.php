<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class TextField extends Field
{
    /** @var int Максимальная длина поля */
    public $maxlength;

    /** @var string Подтип поля */
    public $subtype;

    /** @var boolean Только для чтения */
    public $readonly;

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('text', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        return Html::getAttributeValue($this->model->getModel(), $this->name) ?: '-';
    }

}
