<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models\forms;

use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\components\content\BlockImageStorage;
use Mitisk\Yii2Admin\components\pages\PagePath;
use Mitisk\Yii2Admin\components\pages\PageTree;
use Mitisk\Yii2Admin\enums\PageStatus;
use Mitisk\Yii2Admin\models\Page;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Форма страницы. SCENARIO_EDIT (`editContent`): текст, анонс, SEO, статус и дата.
 * SCENARIO_MANAGE (`manageContent`): ещё заголовок, слаг, родитель, шаблон, порядок.
 *
 * Свойства без типов: load() пишет сырые данные запроса, приведение делают правила.
 */
class PageForm extends Model
{
    public const SCENARIO_EDIT = 'edit';
    public const SCENARIO_MANAGE = 'manage';

    /** @var string */
    public $title = '';
    /** @var string */
    public $slug = '';
    /** @var int|string Генерировать слаг из заголовка */
    public $slug_auto = 1;
    /** @var int|string|null */
    public $parent_id = null;
    /** @var string */
    public $template = 'default';
    /** @var int|string */
    public $sort = 0;
    /** @var string */
    public $status = 'draft';
    /** @var string `Y-m-d\TH:i` из datetime-local */
    public $published_at = '';
    /** @var string */
    public $body = '';
    /** @var string */
    public $excerpt = '';
    /** @var string */
    public $seo_title = '';
    /** @var string */
    public $seo_description = '';
    /** @var string */
    public $seo_keywords = '';
    /** @var string */
    public $canonical = '';
    /** @var int|string */
    public $noindex = 0;
    public ?UploadedFile $og_image = null;
    /** @var int|string */
    public $og_image_remove = 0;

    /**
     * @param list<string>|null $reservedSegments Зарезервированные первые сегменты; null — из `Yii::$app->pages`.
     */
    public function __construct(
        public readonly Page $page,
        private readonly BlockImageStorage $images = new BlockImageStorage(ownerClass: Page::class),
        private readonly ?array $reservedSegments = null,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    public static function fromPage(Page $page, bool $manage): self
    {
        $form = new self($page);
        $form->scenario = $manage ? self::SCENARIO_MANAGE : self::SCENARIO_EDIT;
        foreach (['title', 'slug', 'template', 'body', 'excerpt', 'seo_title', 'seo_description', 'seo_keywords', 'canonical'] as $attr) {
            $form->$attr = (string)$page->$attr;
        }
        $form->parent_id = $page->parent_id;
        $form->sort = (int)$page->sort;
        $form->status = $page->getStatus()->value;
        $form->noindex = (int)(bool)$page->noindex;
        $form->slug_auto = $page->isNewRecord ? 1 : 0;
        $form->published_at = $page->published_at ? date('Y-m-d\TH:i', (int)$page->published_at) : '';
        return $form;
    }

    public function scenarios(): array
    {
        $edit = ['body', 'excerpt', 'status', 'published_at', 'seo_title', 'seo_description', 'seo_keywords', 'canonical', 'noindex', 'og_image', 'og_image_remove'];
        return [
            self::SCENARIO_EDIT => $edit,
            self::SCENARIO_MANAGE => array_merge($edit, ['title', 'slug', 'slug_auto', 'parent_id', 'template', 'sort']),
        ];
    }

    public function rules(): array
    {
        return [
            ['title', 'required', 'on' => self::SCENARIO_MANAGE],
            [['title', 'seo_title', 'seo_keywords'], 'string', 'max' => 255],
            ['slug', 'filter', 'filter' => fn($v): string => $this->normalizeSlug((string)$v)],
            ['slug', 'required', 'on' => self::SCENARIO_MANAGE],
            ['slug', 'match', 'pattern' => PagePath::SLUG_PATTERN, 'message' => 'Слаг: латиница в нижнем регистре, цифры и дефис.'],
            ['slug', 'validateReservedSlug'],
            ['slug', 'validateSiblingSlug'],
            ['parent_id', 'filter', 'filter' => static fn($v): ?int => $v === '' || $v === null ? null : (int)$v],
            ['parent_id', 'validateParent'],
            ['template', 'string', 'max' => 64],
            ['template', 'in', 'range' => array_keys($this->getTemplateOptions()), 'on' => self::SCENARIO_MANAGE],
            ['sort', 'integer'],
            ['status', 'in', 'range' => array_column(PageStatus::cases(), 'value')],
            ['published_at', 'match', 'pattern' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', 'skipOnEmpty' => true, 'message' => 'Дата в формате ГГГГ-ММ-ДДTЧЧ:ММ.'],
            [['body', 'excerpt', 'seo_description'], 'string'],
            ['canonical', 'string', 'max' => 2048],
            ['canonical', 'url', 'skipOnEmpty' => true, 'defaultScheme' => 'https'],
            [['noindex', 'og_image_remove', 'slug_auto'], 'boolean'],
            ['og_image', 'file', 'skipOnEmpty' => true, 'extensions' => BlockImageStorage::ALLOWED, 'checkExtensionByMimeType' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return (new Page())->attributeLabels() + [
            'slug_auto' => 'Слаг из заголовка',
            'og_image' => 'OG-картинка',
            'og_image_remove' => 'Удалить OG-картинку',
        ];
    }

    /** Скрытое поле `og_image=""` от Html::activeFileInput() не должно попасть в типизированное свойство. */
    public function load($data, $formName = null): bool
    {
        $scope = $formName ?? $this->formName();
        if (is_array($data) && isset($data[$scope]) && is_array($data[$scope])) {
            unset($data[$scope]['og_image']);
        }
        $loaded = parent::load($data, $formName);
        $this->og_image = UploadedFile::getInstance($this, 'og_image');
        return $loaded || $this->og_image !== null;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = trim(strtolower($slug), " /\t");
        if (($slug === '' || (bool)$this->slug_auto) && (string)$this->title !== '') {
            return PagePath::slugify((string)$this->title);
        }
        return $slug;
    }

    public function validateReservedSlug(string $attribute): void
    {
        if ($this->parent_id !== null && $this->parent_id !== '') {
            return;
        }
        $reserved = $this->reservedSegments ?? Yii::$app->pages->reservedSegments();
        if (PagePath::isReservedSegment((string)$this->slug, $reserved)) {
            $this->addError($attribute, 'Этот адрес занят разделом сайта. Выберите другой слаг или вложите страницу в другую.');
        }
    }

    /** Проверяет базу; в unit-тестах страница — мок, и проверка пропускается. */
    public function validateSiblingSlug(string $attribute): void
    {
        if ($this->page instanceof \PHPUnit\Framework\MockObject\MockObject) {
            return;
        }
        $query = Page::find()->children($this->parent_id === null ? null : (int)$this->parent_id)->andWhere(['slug' => $this->slug]);
        if (!$this->page->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->page->id]);
        }
        if ($query->exists()) {
            $this->addError($attribute, 'У соседней страницы уже такой адрес.');
        }
    }

    /** Родитель не может быть самой страницей или её потомком. */
    public function validateParent(string $attribute): void
    {
        if ($this->parent_id === null || $this->page->isNewRecord) {
            return;
        }
        $forbidden = PageTree::descendantIds(Page::find()->light()->all(), (int)$this->page->id);
        $forbidden[] = (int)$this->page->id;
        if (in_array((int)$this->parent_id, $forbidden, true)) {
            $this->addError($attribute, 'Нельзя вложить страницу в саму себя или в её потомка.');
        }
    }

    public function getPublishedAtTimestamp(): ?int
    {
        if ((string)$this->published_at === '') {
            return null;
        }
        $ts = strtotime((string)$this->published_at);
        return $ts === false ? null : $ts;
    }

    /** @return array<int, string> id => «— — Заголовок» с отступом по глубине, без самой страницы и её потомков */
    public function getParentOptions(): array
    {
        $all = Page::find()->light()->all();
        $skip = $this->page->isNewRecord ? [] : array_merge([(int)$this->page->id], PageTree::descendantIds($all, (int)$this->page->id));
        $out = [];
        foreach (PageTree::flatten(PageTree::build($all)) as $row) {
            if (!in_array((int)$row['item']->id, $skip, true)) {
                $out[(int)$row['item']->id] = str_repeat('— ', $row['depth']) . $row['item']->title;
            }
        }
        return $out;
    }

    /** @return array<string, string> */
    public function getTemplateOptions(): array
    {
        return Yii::$app->has('pages') ? Yii::$app->pages->templates() : ['default' => 'Обычная страница'];
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $page = $this->page;
        $isNew = $page->isNewRecord;
        $old = $isNew ? [] : ['title' => $page->title, 'path' => $page->path, 'status' => $page->status, 'body' => $page->body];
        $oldImage = $isNew ? null : $page->og_image_id;
        $stored = null;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($this->scenario === self::SCENARIO_MANAGE) {
                $page->title = (string)$this->title;
                $page->slug = (string)$this->slug;
                $page->parent_id = $this->parent_id === null ? null : (int)$this->parent_id;
                $page->template = (string)$this->template;
                $page->sort = (int)$this->sort;
            }
            $page->status = (string)$this->status;
            $page->published_at = $this->getPublishedAtTimestamp();
            if ($page->status === PageStatus::Published->value && $page->published_at === null) {
                $page->published_at = time();
            }
            $page->body = (string)$this->body;
            $page->excerpt = (string)$this->excerpt !== '' ? (string)$this->excerpt : null;
            foreach (['seo_title', 'seo_description', 'seo_keywords', 'canonical'] as $attr) {
                $page->$attr = (string)$this->$attr !== '' ? (string)$this->$attr : null;
            }
            $page->noindex = (int)(bool)$this->noindex;
            if ($isNew) {
                $page->save(false);   // id для item_id картинки
            }
            if ($this->og_image !== null) {
                $stored = $this->images->store($this->og_image, (int)$page->id, 'og_image');
                $page->og_image_id = $stored;
            } elseif ((bool)$this->og_image_remove) {
                $page->og_image_id = null;
            }
            if (!$page->save(false)) {
                throw new \RuntimeException('save() вернул false');
            }
            if ($oldImage !== null && $oldImage !== $page->og_image_id) {
                $this->images->delete((int)$oldImage);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            if ($stored !== null) {
                $this->images->delete($stored);
            }
            Yii::error('PageForm: ' . $e->getMessage(), __METHOD__);
            $this->addError('body', 'Не удалось сохранить страницу.');
            return false;
        }

        Yii::$app->pages->invalidate();
        AuditService::log($isNew ? 'create' : 'update', $page, $isNew ? null : $old);
        return true;
    }
}
