<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\content\BlockValueCodec;
use Mitisk\Yii2Admin\dto\ImageValue;
use Mitisk\Yii2Admin\dto\LinkValue;
use Mitisk\Yii2Admin\dto\ListItem;
use Mitisk\Yii2Admin\enums\BlockType;
use Mitisk\Yii2Admin\models\query\ContentBlockQuery;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\HtmlPurifier;

/**
 * Текстовый блок раздела «Контент».
 *
 * @property int         $id
 * @property string      $key
 * @property string      $name
 * @property string      $type       Значение {@see BlockType}
 * @property string|null $value      Формат по типу — {@see BlockValueCodec}
 * @property string|null $schema     JSON {"itemFields": [...]} для списка
 * @property string      $group
 * @property string|null $hint
 * @property int|bool    $is_active
 * @property int|bool    $from_code
 * @property int         $created_at
 * @property int         $updated_at
 * @property int|null    $updated_by
 *
 * @property-read AdminUser|null $updater
 */
class ContentBlock extends ActiveRecord
{
    /** Тег кэша всех блоков; сбрасывается при любом изменении. */
    public const CACHE_TAG = 'content-block';

    /** Ключ: латиница в нижнем регистре, цифры, точка, дефис, подчёркивание. */
    public const KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,127}$/';

    public static function tableName(): string
    {
        return '{{%content_block}}';
    }

    public static function find(): ContentBlockQuery
    {
        return new ContentBlockQuery(static::class);
    }

    public static function isValidKey(string $key): bool
    {
        return (bool)preg_match(self::KEY_PATTERN, $key);
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
            [
                'class' => BlameableBehavior::class,
                'createdByAttribute' => false,
                'updatedByAttribute' => 'updated_by',
                // Автор — администратор, а не фронтовый пользователь сайта
                'value' => static function (): ?int {
                    if (!Yii::$app->has('adminUser')) {
                        return null;
                    }
                    $user = Yii::$app->get('adminUser');
                    return $user->getIsGuest() ? null : (int)$user->getId();
                },
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['key', 'name', 'type'], 'required'],
            ['key', 'match', 'pattern' => self::KEY_PATTERN, 'message' => 'Ключ: латиница в нижнем регистре, цифры, «.», «-», «_».'],
            ['key', 'unique'],
            ['type', 'in', 'range' => array_column(BlockType::cases(), 'value')],
            [['name', 'hint'], 'string', 'max' => 255],
            ['group', 'string', 'max' => 64],
            [['value', 'schema'], 'string'],
            [['is_active', 'from_code'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'key' => 'Ключ',
            'name' => 'Название',
            'type' => 'Тип',
            'value' => 'Значение',
            'group' => 'Группа',
            'hint' => 'Подсказка',
            'is_active' => 'Активен',
            'from_code' => 'Из кода',
            'updated_at' => 'Изменён',
            'updated_by' => 'Автор правки',
        ];
    }

    public function getBlockType(): BlockType
    {
        return BlockType::tryFrom((string)$this->type) ?? BlockType::Text;
    }

    /**
     * @return string|LinkValue|ImageValue|list<ListItem>
     */
    public function getDecodedValue(): string|LinkValue|ImageValue|array
    {
        return BlockValueCodec::decode($this->getBlockType(), $this->value);
    }

    /**
     * Поля пункта списка, заданные кодом (`itemFields` виджета).
     *
     * @return list<string>
     */
    public function getItemFields(): array
    {
        $data = json_decode((string)$this->schema, true);
        $fields = is_array($data['itemFields'] ?? null) ? $data['itemFields'] : ListItem::FIELDS;
        return array_values(array_intersect(ListItem::FIELDS, $fields)) ?: ListItem::FIELDS;
    }

    /**
     * Id картинок, на которые ссылается значение.
     *
     * @return list<int>
     */
    public function fileIds(): array
    {
        return BlockValueCodec::fileIds($this->getBlockType(), $this->value);
    }

    public function getUpdater(): ActiveQuery
    {
        return $this->hasOne(AdminUser::class, ['id' => 'updated_by']);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($this->group === null || $this->group === '') {
            $this->group = explode('.', (string)$this->key)[0];
        }
        if ($this->getBlockType() === BlockType::Html && $this->value !== null) {
            $this->value = HtmlPurifier::process($this->value);
        }
        return true;
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        self::invalidateCache();
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        // Картинки блока удаляются вместе с ним (File::beforeDelete удаляет и сам файл)
        foreach (File::find()->where(['class_name' => self::class, 'item_id' => $this->id])->each() as $file) {
            $file->delete();
        }
        self::invalidateCache();
    }

    public static function invalidateCache(): void
    {
        if (Yii::$app->has('cache')) {
            TagDependency::invalidate(Yii::$app->cache, self::CACHE_TAG);
        }
    }
}
