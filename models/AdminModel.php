<?php

namespace Mitisk\Yii2Admin\models;

use Yii;

/**
 * This is the model class for table "admin_model".
 *
 * @property int $id
 * @property string $name
 * @property string $alias
 * @property string $table_name
 * @property string $list
 * @property string|null $model_class
 * @property string|null $data
 * @property boolean $in_menu
 * @property boolean $can_create
 * @property int|null $view
 */
class AdminModel extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'admin_model';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            //[['table_name'], 'required'],
            [['view'], 'integer'],
            [['data', 'in_menu', 'can_create', 'list'], 'safe'],
            [['alias'], 'Mitisk\Yii2Admin\components\AliasValidator', 'skipOnEmpty' => false],
            [['alias'], 'unique'],
            [['alias'], 'checkAlias'],
            [['alias', 'model_class', 'name'], 'trim'],
            [['table_name', 'model_class', 'name', 'alias'], 'string', 'max' => 255],
        ];
    }

    public function checkAlias($attribute, $params) {
        if (in_array($this->alias, ['components', 'role', 'user', 'menu', 'auth', 'default'])) {
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
            'in_menu' => '', //Добавить в меню справа
            'can_create' => '', //Можно создавать новые записи
            'list' => 'Колонки в списке',
            'table_name' => 'Таблица',
            'model_class' => 'Class',
            'data' => 'json настроек форм',
            'view' => 'Показывать в списке',
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
}
