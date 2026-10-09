<?php

namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

/**
 * Поле JSON: редактор с подсветкой и клиентской проверкой синтаксиса (Ace,
 * режим json). Значение хранится строкой в атрибуте модели. В просмотре —
 * форматированный (pretty-print) JSON.
 *
 * Авторитетную валидность JSON при необходимости проверяйте правилом в
 * rules() целевой модели.
 */
class JsonField extends Field
{
    /** @var int Высота редактора в строках */
    public $rows = 12;

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('json', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
        ]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $value = (string)Html::getAttributeValue($this->model->getModel(), $this->name);
        if ($value === '') {
            return '-';
        }

        $decoded = json_decode($value, true);
        $pretty = (json_last_error() === JSON_ERROR_NONE)
            ? json_encode(
                $decoded,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
            : $value;

        return '<pre style="margin:0;padding:12px;border-radius:8px;'
            . 'background:#1e253b;color:#e6e8ec;font-size:12px;'
            . 'overflow-x:auto;">' . Html::encode((string)$pretty) . '</pre>';
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
            'value' => function ($data) use ($column) {
                $value = (string)$data->{$column};
                if ($value === '') {
                    return '-';
                }
                return mb_strlen($value) > 60
                    ? mb_substr($value, 0, 60) . '…'
                    : $value;
            },
        ];
    }
}
