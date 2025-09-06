<?php

namespace Mitisk\Yii2Admin\models;

use Yii;

/**
 * This is the model class for table "{{%settings_block}}".
 *
 * @property int $id
 * @property string|null $model_name Имя модели
 * @property string|null $label Заголовок
 * @property string|null $description Описание
 */
class SettingsBlock extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%settings_block}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description'], 'string'],
            [['model_name', 'label'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'model_name' => 'Имя модели',
            'label' => 'Заголовок',
            'description' => 'Описание',
        ];
    }
}
