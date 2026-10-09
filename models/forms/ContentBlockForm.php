<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models\forms;

use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\components\content\BlockImageStorage;
use Mitisk\Yii2Admin\components\content\BlockValueCodec;
use Mitisk\Yii2Admin\dto\ImageValue;
use Mitisk\Yii2Admin\dto\LinkValue;
use Mitisk\Yii2Admin\enums\BlockType;
use Mitisk\Yii2Admin\models\ContentBlock;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Форма блока: поля зависят от типа и не совпадают со строкой таблицы.
 *
 * SCENARIO_EDIT (`editContent`) — только значение и активность.
 * SCENARIO_MANAGE (`manageContent`) — ещё ключ, название, группа, подсказка и тип (при создании).
 *
 * Свойства без типов намеренно: load() пишет сырые данные запроса (могут прийти массивы),
 * приведение и проверку делают правила.
 */
class ContentBlockForm extends Model
{
    public const SCENARIO_EDIT = 'edit';
    public const SCENARIO_MANAGE = 'manage';

    /** @var string */
    public $key = '';
    /** @var string */
    public $name = '';
    /** @var string */
    public $type = 'text';
    /** @var string */
    public $group = '';
    /** @var string */
    public $hint = '';
    /** @var int|string */
    public $is_active = 1;

    /** @var string Значение Text/Html */
    public $text = '';

    /** @var string */
    public $linkText = '';
    /** @var string */
    public $linkUrl = '';
    /** @var string */
    public $linkTarget = '_self';

    /** @var string */
    public $imageAlt = '';
    /** @var string */
    public $imageTitle = '';
    /** @var int|string */
    public $imageRemove = 0;
    public ?UploadedFile $imageFile = null;

    public function __construct(
        public readonly ContentBlock $block,
        private readonly BlockImageStorage $images = new BlockImageStorage(),
        array $config = [],
    ) {
        parent::__construct($config);
    }

    /**
     * Форма, заполненная значениями блока.
     */
    public static function fromBlock(ContentBlock $block, bool $manage): self
    {
        $form = new self($block);
        $form->scenario = $manage ? self::SCENARIO_MANAGE : self::SCENARIO_EDIT;
        $form->key = (string)$block->key;
        $form->name = (string)$block->name;
        $form->type = $block->getBlockType()->value;
        $form->group = (string)$block->group;
        $form->hint = (string)$block->hint;
        $form->is_active = $block->isNewRecord ? 1 : (int)$block->is_active;

        $value = $block->getDecodedValue();
        if (is_string($value)) {
            $form->text = $value;
        } elseif ($value instanceof LinkValue) {
            [$form->linkText, $form->linkUrl, $form->linkTarget] = [$value->text, $value->url, $value->target];
        } else {
            [$form->imageAlt, $form->imageTitle] = [$value->alt, $value->title];
        }
        return $form;
    }

    public function scenarios(): array
    {
        $edit = ['is_active', 'text', 'linkText', 'linkUrl', 'linkTarget', 'imageAlt', 'imageTitle', 'imageRemove', 'imageFile'];
        return [
            self::SCENARIO_EDIT => $edit,
            self::SCENARIO_MANAGE => array_merge($edit, ['key', 'name', 'type', 'group', 'hint']),
        ];
    }

    public function rules(): array
    {
        return [
            [['key', 'name', 'type'], 'required', 'on' => self::SCENARIO_MANAGE],
            ['key', 'match', 'pattern' => ContentBlock::KEY_PATTERN, 'message' => 'Ключ: латиница в нижнем регистре, цифры, «.», «-», «_».'],
            ['key', 'validateUniqueKey'],
            ['type', 'in', 'range' => array_column(BlockType::cases(), 'value')],
            [['name', 'hint', 'linkText', 'imageAlt', 'imageTitle'], 'string', 'max' => 255],
            ['group', 'string', 'max' => 64],
            [['is_active', 'imageRemove'], 'boolean'],
            ['text', 'string'],
            ['linkUrl', 'string', 'max' => 2048],
            ['linkUrl', 'validateLinkUrl'],
            ['linkTarget', 'in', 'range' => ['_self', '_blank']],
            ['imageFile', 'file', 'skipOnEmpty' => true, 'extensions' => BlockImageStorage::ALLOWED, 'checkExtensionByMimeType' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'key' => 'Ключ', 'name' => 'Название', 'type' => 'Тип', 'group' => 'Группа', 'hint' => 'Подсказка',
            'is_active' => 'Активен', 'text' => 'Значение', 'linkText' => 'Текст ссылки', 'linkUrl' => 'Адрес',
            'linkTarget' => 'Открывать', 'imageAlt' => 'Alt', 'imageTitle' => 'Title', 'imageFile' => 'Файл',
            'imageRemove' => 'Удалить картинку',
        ];
    }

    /**
     * Загрузка из запроса браузера.
     *
     * `imageFile` берётся только из `$_FILES`: Html::activeFileInput() шлёт ещё и скрытое
     * поле с тем же именем и пустой строкой, которое нельзя присвоить свойству типа UploadedFile.
     */
    public function load($data, $formName = null): bool
    {
        $scope = $formName ?? $this->formName();
        if (is_array($data) && isset($data[$scope]) && is_array($data[$scope])) {
            unset($data[$scope]['imageFile']);
        }
        $loaded = parent::load($data, $formName);
        $this->imageFile = UploadedFile::getInstance($this, 'imageFile');
        return $loaded || $this->imageFile !== null;
    }

    public function validateUniqueKey(string $attribute): void
    {
        $query = ContentBlock::find()->byKey((string)$this->key);
        if (!$this->block->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->block->id]);
        }
        if ($query->exists()) {
            $this->addError($attribute, 'Блок с таким ключом уже есть.');
        }
    }

    public function validateLinkUrl(string $attribute): void
    {
        $url = trim((string)$this->linkUrl);
        if ($url !== '' && !LinkValue::isSafeUrl($url)) {
            $this->addError($attribute, 'Адрес должен начинаться с /, #, http://, https://, mailto: или tel:.');
        }
    }

    public function getBlockType(): BlockType
    {
        return $this->block->isNewRecord && $this->scenario === self::SCENARIO_MANAGE
            ? (BlockType::tryFrom((string)$this->type) ?? BlockType::Text)
            : $this->block->getBlockType();
    }

    /**
     * Сохраняет блок, картинку и запись аудита в одной транзакции.
     * Загруженный файл при ошибке удаляется.
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $block = $this->block;
        $isNew = $block->isNewRecord;
        $oldValue = $block->value;
        $oldFileIds = $isNew ? [] : $block->fileIds();
        $type = $this->getBlockType();
        $stored = [];

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($this->scenario === self::SCENARIO_MANAGE) {
                $block->key = (string)$this->key;
                $block->name = (string)$this->name;
                $block->group = (string)$this->group;
                $block->hint = (string)$this->hint !== '' ? (string)$this->hint : null;
                if ($isNew) {
                    $block->type = $type->value;
                }
            }
            $block->is_active = (int)(bool)$this->is_active;
            if ($isNew) {
                $block->value = null;
                $block->save(false);   // нужен id для item_id картинки
            }

            $value = match ($type) {
                BlockType::Text, BlockType::Html => (string)$this->text,
                BlockType::Link => new LinkValue(trim((string)$this->linkText), trim((string)$this->linkUrl), (string)$this->linkTarget),
                BlockType::Image => $this->buildImage($oldValue, $stored),
            };
            $block->value = BlockValueCodec::encode($type, $value);
            $block->save(false);

            foreach (array_diff($oldFileIds, $block->fileIds()) as $orphan) {
                $this->images->delete($orphan);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            foreach ($stored as $id) {
                $this->images->delete($id);
            }
            Yii::error('ContentBlockForm: ' . $e->getMessage(), __METHOD__);
            $this->addError('text', 'Не удалось сохранить: ' . $e->getMessage());
            return false;
        }

        AuditService::log($isNew ? 'create' : 'update', $block, $isNew ? null : ['value' => $oldValue]);
        return true;
    }

    /**
     * @param list<int> $stored Id загруженных в этом сохранении файлов.
     */
    private function buildImage(?string $oldValue, array &$stored): ImageValue
    {
        $fileId = BlockValueCodec::decode(BlockType::Image, $oldValue)->fileId;
        if ($this->imageFile !== null) {
            $fileId = $stored[] = $this->images->store($this->imageFile, (int)$this->block->id, 'image');
        } elseif ((bool)$this->imageRemove) {
            $fileId = null;
        }
        return new ImageValue($fileId, trim((string)$this->imageAlt), trim((string)$this->imageTitle));
    }
}
