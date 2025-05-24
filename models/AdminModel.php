<?php

namespace Mitisk\Yii2Admin\models;

use Yii;

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
 */
class AdminModel extends \yii\db\ActiveRecord
{
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
            [['view'], 'integer'],
            [['data', 'in_menu', 'can_create', 'non_encode', 'list'], 'safe'],
            [['alias'], 'Mitisk\Yii2Admin\components\AliasValidator', 'skipOnEmpty' => false],
            [['alias'], 'unique'],
            [['alias'], 'checkAlias'],
            [['alias', 'model_class', 'name', 'admin_label'], 'trim'],
            [['table_name', 'model_class', 'name', 'alias', 'admin_label'], 'string', 'max' => 255],
        ];
    }

    public function checkAlias($attribute, $params) {
        if (in_array($this->alias, ['components', 'role', 'user', 'menu', 'auth', 'ajax', 'default', 'index', 'settings', 'login', 'logout', 'error', 'captcha', 'sitemap', 'contact'])) {
            $this->addError($attribute, 'Этот алиас уже используется');
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
        ];
    }

    public function beforeSave($insert) {
        if($this->data) {
            $this->data = str_replace('\n', '', $this->data);
        }

        if($this->list && is_array($this->list)) {
            $this->list = json_encode($this->list);
        }
        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes) {
        // Проверяем, добавляется ли элемент в меню или его состояние изменяется
        if ($this->in_menu && $this->alias) {
            Menu::addToMenu('admin', [
                'text' => $this->name,
                'href' => '/admin/' . $this->alias . '/',
                'icon' => 'fa fa-list',
                'target' => '_self',
                'title' => $this->name,
            ]);
        } elseif (!$this->in_menu && isset($changedAttributes['in_menu'])) {
            // Удаляем элемент из меню, если флаг in_menu изменился на false
            Menu::removeFromMenu('admin', '/admin/' . $this->alias . '/');
        }
        parent::afterSave($insert, $changedAttributes);
    }
}
