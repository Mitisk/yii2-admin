<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\helpers\Html;

class DateField extends Field
{
    /** @var string Минимальное значение */
    public $min;

    /** @var string Максимальное значение */
    public $max;

    /** @var string Шаг даты */
    public $step;

    /**
     * Whether to show a time picker alongside the date.
     *
     * @var boolean
     */
    public $withTime = false;

    /**
     * Formats a raw DB value (timestamp int or date string) for display.
     *
     * @param mixed $value    Raw attribute value.
     * @param bool  $withTime Include time in output.
     *
     * @return string
     */
    private static function _format($value, bool $withTime): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $ts = is_numeric($value) ? (int)$value : @strtotime((string)$value);

        if (!$ts) {
            return (string)$value;
        }

        return $withTime ? date('d.m.Y H:i:s', $ts) : date('d.m.Y', $ts);
    }

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    /**
     * Строит HTML для фильтра даты с переключателем оператора.
     *
     * @param string $formName FormName модели
     * @param string $column   Имя атрибута
     *
     * @return string
     */
    private static function _buildDateFilter(
        string $formName,
        string $column
    ): string {
        $opName = '_df_op[' . $column . ']';
        $opVal = \Yii::$app->request->get('_df_op')[$column]
            ?? '=';
        $dateVal = \Yii::$app->request->get($formName)[$column]
            ?? '';

        $ops = ['=' => '=', '>=' => '≥', '<=' => '≤'];
        $btns = '';
        foreach ($ops as $k => $label) {
            $active = ($opVal === $k) ? ' active' : '';
            $btns .= '<button type="button"'
                . ' class="date-op-btn' . $active . '"'
                . ' data-op="' . Html::encode($k) . '">'
                . $label . '</button>';
        }

        return '<div class="date-filter-wrap">'
            . '<div class="date-op-group">' . $btns . '</div>'
            . '<input type="hidden" name="'
            . Html::encode($opName) . '" value="'
            . Html::encode($opVal) . '" class="date-op-hidden">'
            . '<input type="date" class="form-control" name="'
            . Html::encode($formName . '[' . $column . ']')
            . '" value="' . Html::encode($dateVal) . '">'
            . '</div>';
    }

    public function renderList(string $column): array
    {
        $withTime = (bool)$this->withTime;
        $formName = $this->model->getModel()->formName();
        $filterHtml = self::_buildDateFilter($formName, $column);

        return [
            'attribute' => $column,
            'format'    => 'raw',
            'filter'    => $filterHtml,
            'value'     => static function ($data) use ($column, $withTime) {
                $formatted = self::_format($data->{$column}, $withTime);
                if ($formatted === '-') {
                    return '<span class="date-cell">&mdash;</span>';
                }
                $icon = $withTime
                    ? '<i class="icon-clock"></i> '
                    : '<i class="icon-calendar"></i> ';
                return '<span class="date-cell">'
                    . $icon . Html::encode($formatted)
                    . '</span>';
            },
        ];
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        $raw = Html::getAttributeValue($this->model->getModel(), $this->name);

        // Detect column type to know if it is stored as unix timestamp
        $tableSchema = $this->model->getModel()->getTableSchema();
        $colInfo     = $tableSchema
            ? ($tableSchema->columns[$this->name] ?? null)
            : null;
        $isTimestamp = $colInfo
            && in_array($colInfo->type, ['integer', 'bigint', 'smallint'], true);

        // Fallback: numeric value with reasonable timestamp range
        if (!$isTimestamp && is_numeric($raw) && (int)$raw > 1_000_000) {
            $isTimestamp = true;
        }

        $ts = $raw ? (is_numeric($raw) ? (int)$raw : @strtotime((string)$raw)) : 0;

        if ($this->withTime) {
            $inputType      = 'datetime-local';
            $formattedValue = $ts ? date('Y-m-d\TH:i:s', $ts) : '';
        } else {
            $inputType      = 'date';
            $formattedValue = $ts ? date('Y-m-d', $ts) : '';
        }

        return $this->render(
            'date',
            [
                'field'          => $this,
                'model'          => $this->model,
                'fieldId'        => $this->fieldId,
                'rawValue'       => $raw,
                'formattedValue' => $formattedValue,
                'inputType'      => $inputType,
                'isTimestamp'    => $isTimestamp,
            ]
        );
    }

    /**
     * Renders field HTML for the detail view.
     *
     * @return string
     */
    public function renderView(): string
    {
        $value = Html::getAttributeValue($this->model->getModel(), $this->name);
        return self::_format($value, (bool)$this->withTime);
    }
}
