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
     * Конструктор
     * @return void
     */
    public function getFormInput()
    {
        $name = ArrayHelper::getValue($this->input, 'type', 'text');

        $fieldName = ucfirst($name).'Field';
        if($fieldName = static::resolveFiedClass($fieldName)) {
            $fieldClass = Yii::createObject(['class' => $fieldName], [$this->input]);
            $fieldClass->model = $this->model;

            if($fieldClass->name && $this->model->getModel()->hasAttribute($fieldClass->name)) {
                $fieldClass->fieldId = Html::getInputId($this->model->getModel(), $fieldClass->name);
            } else {
                $fieldClass->fieldId = Yii::$app->security->generateRandomString();
            }

            return '<fieldset class="' . FieldsHelper::getColumns($fieldClass->className) .'">' . $fieldClass->renderField() . '</fieldset>';
        }

        return '<div class="form-group">Нет описания поля ' . $name . '</div>';
    }


    /**
     * @param string $class
     * @return string|null
     */
    public static function resolveFiedClass($class)
    {
        $classname = null;
        if(class_exists($class)) {
            $classname = $class;
        } elseif(class_exists('Mitisk\\Yii2Admin\\fields\\'.$class)) {
            $classname = 'Mitisk\\Yii2Admin\\fields\\'.$class;
        } else {}
        return $classname;
    }

    /**
     * @return string
     */
    public function renderField()
    {
        return '<div class="form-group">Нет описания поля ' . $this->name . '</div>';
    }
}