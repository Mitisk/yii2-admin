<?php

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\ReservedAlias;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "admin_model".
 *
 * @property int $id
 * @property string $name
 * @property string $admin_label
 * @property string $alias
 * @property string $table_name
 * @property string $list
 * @property string|null $model_class
 * @property string|null $data
 * @property boolean $in_menu
 * @property boolean $can_create
 * @property boolean $non_encode
 * @property int|null $view
 * @property string $default_sort_attribute
 * @property int $default_sort_direction
 */
class AdminModel extends \yii\db\ActiveRecord
{
    /** @var bool Можно ли просматривать записи */
    public $can_view = true;

    /** @var bool Можно ли редактировать записи */
    public $can_update = true;

    /** @var bool Можно ли удалять записи */
    public $can_delete = true;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%admin_model}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            //[['table_name'], 'required'],
            [['view', 'default_sort_direction'], 'integer'],
            [['data', 'in_menu', 'can_create', 'non_encode', 'list', 'attribute_labels'], 'safe'],
            [['alias'], 'Mitisk\Yii2Admin\components\AliasValidator', 'skipOnEmpty' => false],
            [['alias'], 'unique'],
            ['alias', fn($attribute) => \Mitisk\Yii2Admin\components\ReservedAlias::validateForModel($this, $attribute)],
            [['alias', 'model_class', 'name', 'admin_label'], 'trim'],
            [['table_name', 'model_class', 'name', 'alias', 'admin_label', 'default_sort_attribute'], 'string', 'max' => 255],
            [['default_sort_direction'], 'default', 'value' => SORT_ASC],
        ];
    }

    public function afterFind()
    {
        parent::afterFind();
        if ($this->list) {
            $list = json_decode($this->list, true);
            if (ArrayHelper::getValue($list, 'admin_actions')) {
                if (!filter_var(ArrayHelper::getValue($list, 'admin_actions.on'), FILTER_VALIDATE_BOOLEAN)) {
                    $this->can_update = false;
                    $this->can_delete = false;
                    $this->can_view = false;
                } else {
                    $this->can_update = filter_var(ArrayHelper::getValue($list, 'admin_actions.data.update'), FILTER_VALIDATE_BOOLEAN);
                    $this->can_delete = filter_var(ArrayHelper::getValue($list, 'admin_actions.data.delete'), FILTER_VALIDATE_BOOLEAN);
                    $this->can_view = filter_var(ArrayHelper::getValue($list, 'admin_actions.data.view'), FILTER_VALIDATE_BOOLEAN);
                }
            }
        }
        if ($this->attribute_labels) {
            $this->attribute_labels = json_decode($this->attribute_labels, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'alias' => 'Алиас',
            'in_menu' => '', //Добавить в меню слева
            'can_create' => '', //Можно создавать новые записи
            'non_encode' => '', //Разрешить вывод данных без экранирования
            'list' => 'Колонки в списке',
            'table_name' => 'Таблица',
            'model_class' => 'Class',
            'data' => 'json настроек форм',
            'view' => 'Показывать в списке',
            'admin_label' => 'Название по установленному полю в панели администратора',
            'default_sort_attribute' => 'Поле сортировки',
            'default_sort_direction' => 'Порядок сортировки',
            'attribute_labels' => 'Названия полей',
        ];
    }

    public function beforeSave($insert) {
        if($this->data) {
            $this->data = str_replace('\n', '', $this->data);
        }

        if($this->list && is_array($this->list)) {
            $this->list = json_encode($this->list, JSON_UNESCAPED_UNICODE);
        }

        if ($this->attribute_labels && is_array($this->attribute_labels)) {
            $this->attribute_labels = json_encode($this->attribute_labels, JSON_UNESCAPED_UNICODE);
        }

        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes) {
        // Проверяем, добавляется ли элемент в меню или его состояние изменяется
        if ($this->in_menu && $this->alias) {
            Menu::addToMenu('admin', [
                'text' => $this->name,
                'href' => '/admin/' . $this->alias . '/',
                'icon' => 'far fa-circle',
                'target' => '_self',
                'rule' => $this->model_class . '\view',
                'title' => $this->name,
            ]);
        } elseif (!$this->in_menu && isset($changedAttributes['in_menu'])) {
            // Удаляем элемент из меню, если флаг in_menu изменился на false
            Menu::removeFromMenu('admin', '/admin/' . $this->alias . '/');
        }
        parent::afterSave($insert, $changedAttributes);
    }
}
