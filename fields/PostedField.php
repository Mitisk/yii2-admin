<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\Html;

class PostedField extends Field
{
    /** @var string Wrapper Class */
    public $wrapperClass = 'fieldset-toggle';

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    public function renderList(string $column): array
    {
        $knownNames = ['active', 'published', 'status'];
        $isKnown = in_array($column, $knownNames, true);
        $onLabel = $isKnown ? 'Активно' : Html::encode($this->label);
        $offLabel = $isKnown ? 'Неактивно' : Html::encode($this->label);

        return [
            'attribute' => $column,
            'format' => 'raw',
            'filter' => ['' => '---', '1' => $onLabel, '0' => $offLabel],
            'value' => function ($data) use ($column, $isKnown, $onLabel, $offLabel) {
                $isActive = (bool)$data->{$column};

                $labelText = $isActive ? $onLabel : $offLabel;

                if (!(\Yii::$app->user->can(get_class($data) . '\update') || \Yii::$app->user->can('admin'))) {
                    $cls = $isActive ? 'block-available' : 'block-not-available';
                    return '<div class="' . $cls . '">' . $labelText . '</div>';
                }

                $statusClass = $isActive ? 'block-available' : 'block-not-available';
                $checked = $isActive ? 'checked' : '';

                $id = $data->getPrimaryKey();
                $modelClass = addslashes(get_class($data));

                return <<<HTML
<div class="toggle-status-block {$statusClass}" data-id="{$id}" data-model="{$modelClass}" data-attribute="{$column}">
    <label class="list-toggle-switch">
        <input type="checkbox" {$checked} autocomplete="off" class="js-toggle-published">
        <span class="list-toggle-label">{$labelText}</span>
    </label>
</div>
HTML;
            }
        ];
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('posted', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $value = Html::getAttributeValue(
            $this->model->getModel(),
            $this->name
        );

        $knownNames = ['active', 'published', 'status'];
        if (in_array($this->name, $knownNames, true)) {
            $label = $value ? 'Активно' : 'Неактивно';
        } else {
            $label = Html::encode($this->label);
        }

        $cls = $value
            ? 'block-available'
            : 'block-not-available';
        return '<div class="' . $cls . '">'
            . $label . '</div>';
    }
}
