<?php

namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

/**
 * Поле телефона: инпут type=tel + tel:-ссылка в просмотре/списке.
 *
 * Клиентская валидация — HTML5 (type=tel, опциональный pattern).
 * Серверную валидацию формата задавайте правилом ['match'] в rules()
 * целевой модели.
 */
class PhoneField extends TextField
{
    /** @var string */
    public $inputType = 'tel';

    /**
     * Рендерит значение tel:-ссылкой.
     *
     * @param string $value Значение
     * @return string
     */
    private function renderLink(string $value): string
    {
        if ($value === '') {
            return '-';
        }
        $tel = preg_replace('/[^\d+]/', '', $value);
        return Html::a(Html::encode($value), 'tel:' . $tel);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        return $this->renderLink(
            (string)Html::getAttributeValue($this->model->getModel(), $this->name)
        );
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
            'value' => fn($data) => $this->renderLink((string)$data->{$column}),
        ];
    }
}
