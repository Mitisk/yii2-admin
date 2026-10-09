<?php

namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

/**
 * Поле URL: инпут type=url + кликабельная ссылка в просмотре/списке.
 *
 * Ссылкой рендерится только http/https-значение (иначе — просто текст),
 * чтобы исключить javascript:-схему. Серверную валидацию задавайте
 * правилом ['url'] в rules() целевой модели.
 */
class UrlField extends TextField
{
    /** @var string */
    public $inputType = 'url';

    /**
     * Рендерит значение ссылкой (только для http/https).
     *
     * @param string $value Значение
     * @return string
     */
    private function renderLink(string $value): string
    {
        if ($value === '') {
            return '-';
        }
        if (!preg_match('~^https?://~i', $value)) {
            return Html::encode($value);
        }
        return Html::a(Html::encode($value), $value, [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ]);
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
