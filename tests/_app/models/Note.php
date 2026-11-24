<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Тестовая сущность для универсального CRUD.
 *
 * @property int $id
 * @property string $title
 * @property int $published
 * @property int $archived
 * @property array|null $tags JSON-колонка: Yii отдаёт и принимает массив
 * @property int|null $author_id пользователь админки (поле типа user)
 * @property int|null $updated_at время правки (TimestampBehavior)
 */
final class Note extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%test_note}}';
    }

    public function behaviors(): array
    {
        return [
            ['class' => TimestampBehavior::class, 'createdAtAttribute' => false],
        ];
    }

    public function rules(): array
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['published', 'archived', 'author_id'], 'integer'],
            [['published', 'archived'], 'in', 'range' => [0, 1]],
            [['tags'], 'safe'],
        ];
    }
}
