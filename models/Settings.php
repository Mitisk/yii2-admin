<?php

namespace Mitisk\Yii2Admin\models;

use Yii;

/**
 * This is the model class for table "{{%settings}}".
 *
 * @property int $id
 * @property string $model_name Имя модели
 * @property string $attribute Название параметра
 * @property string|null $value Значение
 * @property string $type Тип данных
 * @property string|null $label Человекочитаемое название
 * @property string|null $description Описание параметра
 * @property int|null $updated_at Время последнего изменения
 */
class Settings extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName() : string
    {
        return '{{%settings}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['model_name', 'attribute'], 'required'],
            [['value', 'description'], 'string'],
            [['updated_at'], 'integer'],
            [['model_name', 'attribute', 'label'], 'string', 'max' => 255],
            [['type'], 'string', 'max' => 32],
            [['model_name', 'attribute'], 'unique', 'targetAttribute' => ['model_name', 'attribute']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'id' => 'ID',
            'model_name' => 'Имя модели',
            'attribute' => 'Название параметра',
            'value' => 'Значение',
            'type' => 'Тип данных',
            'label' => 'Человекочитаемое название',
            'description' => 'Описание параметра',
            'updated_at' => 'Время последнего изменения',
        ];
    }

    /**
     * Получить значение настройки
     * @param string|null $modelName
     * @param string $attribute
     * @param mixed|null $default
     * @return mixed
     */
    public static function getValue(string|null $modelName = null, string $attribute = '', mixed $default = null) : mixed
    {
        if ($modelName) {
            $setting = static::findOne(['model_name' => $modelName, 'attribute' => $attribute]);
        } else {
            $setting = static::findOne(['attribute' => $attribute]);
        }

        if ($setting === null) {
            return $default;
        }
        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Установить значение настройки
     * @param string $modelName
     * @param string $attribute
     * @param mixed $value
     * @param string $type
     * @return bool
     * @throws \yii\db\Exception
     */
    public static function setValue(string $modelName, string $attribute, mixed $value, string $type = 'string') : bool
    {
        $setting = static::findOne(['model_name' => $modelName, 'attribute' => $attribute]);
        if ($setting === null) {
            $setting = new static([
                'model_name' => $modelName,
                'attribute' => $attribute,
                'type' => $type,
                'updated_at' => time(),
            ]);
        }
        $setting->value = (string)$value;
        $setting->type = $type;
        $setting->updated_at = time();
        return $setting->save();
    }

    /**
     * Приведение значения к нужному типу
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public static function castValue(mixed $value, string $type) : mixed
    {
        switch ($type) {
            case 'integer':
                return (int)$value;
            case 'boolean':
                return $value === '1' || $value === 'true' || $value === 'on';
            case 'float':
                return (float)$value;
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Получить все настройки для модели
     * @param $modelName
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function getModelSettings(string $modelName) : array
    {
        return static::find()->where(['model_name' => $modelName])->indexBy('attribute')->all();
    }
}
