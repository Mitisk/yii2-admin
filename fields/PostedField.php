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
        return [
            'attribute' => $column,
            'format' => 'raw',
            'value' => function ($data) use ($column) {
                $isActive = (bool)$data->{$column};

                if (!(\Yii::$app->user->can(get_class($data) . '\update') || \Yii::$app->user->can('admin'))) {
                     return $isActive 
                        ? '<div class="block-available">Активно</div>'
                        : '<div class="block-not-available">Неактивно</div>';
                }

                $statusClass = $isActive ? 'block-available' : 'block-not-available';
                $labelText = $isActive ? 'Активно' : 'Неактивно';
                $checked = $isActive ? 'checked' : '';
                
                // Get the primary key for the AJAX update
                $id = $data->getPrimaryKey();
                // Model class name for the controller to know what to update
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
        $value = Html::getAttributeValue($this->model->getModel(), $this->name);
        return $value
            ? '<div class="block-available">Активно</div>'
            : '<div class="block-not-available">Неактивно</div>';
    }
}
