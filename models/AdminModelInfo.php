<?php

namespace Mitisk\Yii2Admin\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "admin_model_info".
 *
 * @property int $id
 * @property string $model_class
 * @property string|null $content
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class AdminModelInfo extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%admin_model_info}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['model_class'], 'required'],
            [['content'], 'string'],
            [['created_at', 'updated_at'], 'integer'],
            [['model_class'], 'string', 'max' => 255],
            [['model_class'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'model_class' => 'Model Class',
            'content' => 'Инструкция',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
