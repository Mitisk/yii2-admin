<?php

namespace Mitisk\Yii2Admin\models;

use Yii;

/**
 * This is the model class for table "{{%menu}}".
 *
 * @property int $id
 * @property string|null $name Название
 * @property string|null $alias Алиас
 * @property string|null $data Данные
 * @property bool $not_editable Нельзя удалить и редактировать название
 * @property int|null $ordering Сортировка
 */
class Menu extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%menu}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['data'], 'string'],
            [['ordering', 'not_editable'], 'integer'],
            [['alias'], 'Mitisk\Yii2Admin\components\AliasValidator', 'skipOnEmpty' => false],
            [['alias'], 'unique'],
            [['name', 'alias'], 'string', 'max' => 255],
        ];
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
            'data' => 'Данные',
            'not_editable' => 'Нельзя удалить и редактировать название',
            'ordering' => 'Сортировка',
        ];
    }
}
