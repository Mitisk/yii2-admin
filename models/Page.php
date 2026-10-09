<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\pages\PageHtmlPurifier;
use Mitisk\Yii2Admin\components\pages\PagePath;
use Mitisk\Yii2Admin\enums\PageStatus;
use Mitisk\Yii2Admin\models\query\PageQuery;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Страница раздела «Контент».
 *
 * @property int         $id
 * @property int|null    $parent_id
 * @property string      $slug
 * @property string      $path          Полный адрес без слешей по краям, считается из родителя и слага
 * @property string      $title
 * @property string|null $body
 * @property string|null $excerpt
 * @property string      $template
 * @property string      $status        Значение {@see PageStatus}
 * @property int|null    $published_at
 * @property int         $sort
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property int|null    $og_image_id
 * @property string|null $canonical
 * @property int|bool    $noindex
 * @property int         $created_at
 * @property int         $updated_at
 * @property int|null    $created_by
 * @property int|null    $updated_by
 *
 * @property-read Page|null      $parent
 * @property-read Page[]         $children
 * @property-read AdminUser|null $updater
 */
class Page extends ActiveRecord
{
    public const CACHE_TAG = 'page';

    /** Адрес до сохранения — для авторедиректов. */
    private ?string $_oldPath = null;

    public static function tableName(): string
    {
        return '{{%page}}';
    }

    public static function find(): PageQuery
    {
        return new PageQuery(static::class);
    }

    public function behaviors(): array
    {
        $adminId = static function (): ?int {
            if (!Yii::$app->has('adminUser')) {
                return null;
            }
            $user = Yii::$app->get('adminUser');
            return $user->getIsGuest() ? null : (int)$user->getId();
        };
        return [
            TimestampBehavior::class,
            ['class' => BlameableBehavior::class, 'value' => $adminId],
        ];
    }

    public function rules(): array
    {
        return [
            [['slug', 'title'], 'required'],
            ['slug', 'match', 'pattern' => PagePath::SLUG_PATTERN, 'message' => 'Слаг: латиница в нижнем регистре, цифры и дефис.'],
            ['slug', 'validateSiblingSlug'],
            ['parent_id', 'integer'],
            ['parent_id', 'exist', 'targetClass' => self::class, 'targetAttribute' => 'id', 'skipOnEmpty' => true],
            ['title', 'string', 'max' => 255],
            [['body', 'excerpt', 'seo_description'], 'string'],
            ['template', 'string', 'max' => 64],
            ['template', 'default', 'value' => 'default'],
            ['status', 'in', 'range' => array_column(PageStatus::cases(), 'value')],
            [['published_at', 'sort', 'og_image_id'], 'integer'],
            ['sort', 'default', 'value' => 0],
            [['seo_title', 'seo_keywords'], 'string', 'max' => 255],
            ['canonical', 'string', 'max' => 2048],
            ['noindex', 'boolean'],
        ];
    }

    /**
     * Сохранение и удаление — в транзакции: пересчёт путей ветки, редиректы и удаление
     * потомков либо проходят целиком, либо не проходят вовсе. Внутри внешней транзакции
     * (форма, удаление из списка) Yii делает точку сохранения.
     */
    public function transactions(): array
    {
        return [self::SCENARIO_DEFAULT => self::OP_ALL];
    }

    public function attributeLabels(): array
    {
        return [
            'parent_id' => 'Родитель', 'slug' => 'Адрес (слаг)', 'path' => 'Адрес', 'title' => 'Заголовок',
            'body' => 'Текст', 'excerpt' => 'Анонс', 'template' => 'Шаблон', 'status' => 'Статус',
            'published_at' => 'Дата публикации', 'sort' => 'Порядок', 'seo_title' => 'SEO title',
            'seo_description' => 'SEO description', 'seo_keywords' => 'Ключевые слова', 'og_image_id' => 'OG-картинка',
            'canonical' => 'Canonical', 'noindex' => 'Не индексировать', 'updated_at' => 'Изменена', 'updated_by' => 'Автор правки',
        ];
    }

    /** Слаг уникален среди соседей (индекс БД не ловит корневые страницы с NULL-родителем). */
    public function validateSiblingSlug(string $attribute): void
    {
        $query = static::find()->children($this->parent_id === null ? null : (int)$this->parent_id)->andWhere(['slug' => $this->slug]);
        if (!$this->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->id]);
        }
        if ($query->exists()) {
            $this->addError($attribute, 'У соседней страницы уже такой адрес.');
        }
    }

    public function getStatus(): PageStatus
    {
        return PageStatus::tryFrom((string)$this->status) ?? PageStatus::Draft;
    }

    /** Опубликована и срок публикации наступил. */
    public function isLive(?int $now = null): bool
    {
        return $this->getStatus() === PageStatus::Published
            && ($this->published_at === null || (int)$this->published_at <= ($now ?? time()));
    }

    public function getUrl(): string
    {
        return '/' . $this->path;
    }

    public function getParent(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren(): ActiveQuery
    {
        return $this->hasMany(self::class, ['parent_id' => 'id'])->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getUpdater(): ActiveQuery
    {
        return $this->hasOne(AdminUser::class, ['id' => 'updated_by']);
    }

    public function getOgImageUrl(): ?string
    {
        return $this->og_image_id ? File::findOne((int)$this->og_image_id)?->getUrl() : null;
    }

    /**
     * Пересчёт адреса и последняя линия защиты дерева: любой путь записи (форма, бар,
     * код сайта, `save(false)`) не может зациклить дерево, повесить страницу на
     * несуществующего родителя или занять корневой адрес раздела сайта.
     * Причина отказа — в `getErrors()`, `save()` вернёт false.
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->_oldPath = $insert ? null : (string)$this->getOldAttribute('path');
        if ($this->parent_id !== null && (int)$this->parent_id <= 0) {
            $this->parent_id = null;
        }

        $parentPath = null;
        if ($this->parent_id !== null) {
            $found = static::find()->select('path')->where(['id' => (int)$this->parent_id])->scalar();
            if ($found === false || $found === null) {
                $this->addError('parent_id', 'Родительская страница не найдена.');
                return false;
            }
            $parentPath = (string)$found;
            $isSelfOrDescendant = (int)$this->parent_id === (int)$this->id
                || ($this->_oldPath !== null && ($parentPath === $this->_oldPath || str_starts_with($parentPath, $this->_oldPath . '/')));
            if (!$insert && $isSelfOrDescendant) {
                $this->addError('parent_id', 'Нельзя вложить страницу в саму себя или в её потомка.');
                return false;
            }
        }

        $this->path = PagePath::join($parentPath, (string)$this->slug);
        if ($parentPath === null && $this->path !== $this->_oldPath && Yii::$app->has('pages')
            && PagePath::isReservedSegment((string)$this->slug, Yii::$app->get('pages')->reservedSegments())
        ) {
            $this->addError('slug', 'Этот адрес занят разделом сайта.');
            return false;
        }

        if ($this->body !== null) {
            $this->body = PageHtmlPurifier::process($this->body, Yii::$app->pages->iframeHosts());
        }
        return true;
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        // Сохранили списком атрибутов без path (save(false, ['slug'])): путь всё равно должен попасть в БД
        if (!$insert && $this->getOldAttribute('path') !== $this->path) {
            static::updateAll(['path' => $this->path], ['id' => $this->id]);
            $this->setOldAttribute('path', $this->path);
        }
        if ($this->_oldPath !== null && $this->_oldPath !== $this->path) {
            $this->moveSubtree($this->_oldPath, $this->path);
        }
        // Страница заняла адрес, с которого был редирект — страница главнее
        PageRedirect::deleteAll(['from_path' => $this->path]);
        self::invalidateCache();
    }

    /**
     * Адрес изменился: пересчитать пути потомков и записать редиректы со старых адресов.
     */
    private function moveSubtree(string $oldPath, string $newPath): void
    {
        PageRedirect::upsert($oldPath, $newPath, (int)$this->id);
        foreach (static::find()->light()->andWhere(['like', 'path', $oldPath . '/%', false])->all() as $child) {
            $childNew = $newPath . substr($child->path, strlen($oldPath));
            PageRedirect::upsert($child->path, $childNew, (int)$child->id);
            static::updateAll(['path' => $childNew], ['id' => $child->id]);
            // Потомок занял адрес — чужой редирект с этого адреса больше не нужен
            PageRedirect::deleteAll(['from_path' => $childNew]);
        }
        PageRedirect::deleteAll(['from_path' => $newPath]);
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        // Поддерево удаляется вместе со страницей (каждый потомок — через свой afterDelete)
        foreach (static::find()->children((int)$this->id)->all() as $child) {
            $child->delete();
        }
        PageRedirect::deleteAll(['page_id' => $this->id]);
        if ($this->og_image_id) {
            File::findOne(['id' => (int)$this->og_image_id, 'class_name' => self::class])?->delete();
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
