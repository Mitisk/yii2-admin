<?php
namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\base\Widget;

class Field extends Widget
{
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
}