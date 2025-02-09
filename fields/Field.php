<?php
namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Yii;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class Field extends Widget
{
    /** @var array Изначальный массив настроек поля */
    public $input;
    /** @var AdminModel Модель */
    public $model;
    /** @var string Тип поля */
    public $type;
    /** @var boolean Обязательность поля */
    public $required;
    /** @var string Название поля */
    public $label;
    /** @var string Класс поля */
    public $className;
    /** @var string Имя поля */
    public $name;
    /** @var string Help Text */
    public $description;
    /** @var string Placeholder */
    public $placeholder;
    /** @var boolean Используется ли rbac */
    public $access;
    /** @var string Роли, которые видят поле. Через запятую */
    public $role;
    /** @var string Значение поля */
    public $value;
    /** @var string Идентификатор поля */
    public $fieldId;

    /**
     * Значение для листинга
     * @param string $column Выводимое поле
     * @return array|string
     */
    public function getListData(string $column): array
    {
        $fieldClass = $this->buildField();
        $fieldClass->model = $this->model;

        return $fieldClass->renderList($column);
    }

    /**
     * Значение для просмотра
     * @return string
     */
    public function getViewData(): string
    {
        $fieldClass = $this->buildField();
        $fieldClass->model = $this->model;

        if($fieldClass->name && $this->model->getModel()->hasAttribute($fieldClass->name)) {
            $fieldClass->fieldId = Html::getInputId($this->model->getModel(), $fieldClass->name);
        } else {
            $fieldClass->fieldId = Yii::$app->security->generateRandomString();
        }

        return $fieldClass->renderView();
    }

    /**
     * Рендер поля
     * @return string
     */
    public function getFormInput(): string
    {
        $fieldClass = $this->buildField();
        $fieldClass->model = $this->model;

        if($fieldClass->name && $this->model->getModel()->hasAttribute($fieldClass->name)) {
            $fieldClass->fieldId = Html::getInputId($this->model->getModel(), $fieldClass->name);
        } else {
            $fieldClass->fieldId = Yii::$app->security->generateRandomString();
        }

        return '<fieldset class="' . FieldsHelper::getColumns($fieldClass->className) .'">' . $fieldClass->renderField() . '</fieldset>';
    }

    /**
     * @return Field
     */
    private function buildField(): Field
    {
        $name = str_replace('-', ' ', ArrayHelper::getValue($this->input, 'type', 'text'));

        $fieldName = static::resolveFieldClass(str_replace(' ', '', ucfirst($name)).'Field');

        return Yii::createObject(['class' => $fieldName], [$this->input]);
    }

    /**
     * Отдавет название поля
     * @return string
     */
    public function getLabel(): string
    {
        $fieldName = ArrayHelper::getValue($this->input, 'name');
        if($fieldName) {
            return $this->model->getModel()->getAttributeLabel($fieldName);
        }
        return ArrayHelper::getValue($this->input, 'label');
    }

    /**
     * @param string $class
     * @return string|null
     */
    public static function resolveFieldClass(string $class): string|null
    {
        $classname = 'Mitisk\\Yii2Admin\\fields\\Field';
        if(class_exists($class)) {
            $classname = $class;
        } elseif(class_exists('Mitisk\\Yii2Admin\\fields\\'.$class)) {
            $classname = 'Mitisk\\Yii2Admin\\fields\\'.$class;
        }
        return $classname;
    }

    /**
     * Вывод поля в списке
     * @return string
     */

    /**
     * Вывод поля в списке
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    public function renderList(string $column): array
    {
        return [
            'attribute' => $column
        ];
    }

    /**
     * Вывод поля при редактировании
     * @return string
     */
    public function renderField(): string
    {
        return '<div class="form-group">Нет описания поля ' . $this->name . '</div>';
    }

    /**
     * Вывод поля при просмотре
     * @return string
     */
    public function renderView(): string
    {
        return '<div class="form-group">Нет описания поля ' . $this->name . '</div>';
    }
}
