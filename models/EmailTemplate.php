<?php

namespace Mitisk\Yii2Admin\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "email_templates".
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $subject
 * @property string|null $body
 * @property array|null $params
 * @property int|null $active
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class EmailTemplate extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%email_templates}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['slug', 'name', 'subject'], 'required'],
            [['body'], 'string'],
            [['params'], 'safe'], // JSON field handled as array
            [['active', 'created_at', 'updated_at'], 'integer'],
            [['slug'], 'string', 'max' => 100],
            [['name', 'subject'], 'string', 'max' => 255],
            [['slug'], 'unique'],
            [['slug'], 'match', 'pattern' => '/^[a-z0-9_-]+$/', 'message' => 'Slug может содержать только латинские буквы, цифры, дефис и подчеркивание.'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'slug' => 'Код (Slug)',
            'name' => 'Название',
            'subject' => 'Тема письма',
            'body' => 'Шаблон письма',
            'params' => 'Переменные',
            'active' => 'Активен',
            'created_at' => 'Создан',
            'updated_at' => 'Обновлен',
        ];
    }
}