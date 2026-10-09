<?php

namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

/**
 * Поле email: инпут type=email + вывод mailto-ссылки.
 *
 * Клиентская валидация — HTML5 (type=email). Серверную (авторитетную)
 * валидацию задавайте правилом ['email'] в rules() целевой модели.
 */
class EmailField extends TextField
{
    /** @var string */
    public $inputType = 'email';

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $value = (string)Html::getAttributeValue($this->model->getModel(), $this->name);
        return $value === '' ? '-' : Html::mailto(Html::encode($value), $value);
    }

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array
     */
    public function renderList(string $column): array
    {
        return [
            'attribute' => $column,
            'format' => 'raw',
            'value' => function ($data) use ($column) {
                $value = (string)$data->{$column};
                return $value === '' ? '-' : Html::mailto(Html::encode($value), $value);
            },
        ];
    }
}
