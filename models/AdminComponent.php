<?php

namespace Mitisk\Yii2Admin\models;

/**
 * This is the model class for table "{{%admin_component}}".
 *
 * @property int $id
 * @property string $alias
 * @property string $name
 * @property string|null $version
 * @property string $datetime
 */
class AdminComponent extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%admin_component}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['alias', 'name'], 'required'],
            [['datetime'], 'safe'],
            [['alias', 'name', 'version'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'alias' => 'Alias',
            'name' => 'Name',
            'version' => 'Version',
            'datetime' => 'Datetime',
        ];
    }
}
