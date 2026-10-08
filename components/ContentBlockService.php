<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\components\content\BlockRenderer;
use Mitisk\Yii2Admin\components\content\BlockValueCodec;
use Mitisk\Yii2Admin\dto\LinkValue;
use Mitisk\Yii2Admin\enums\BlockType;
use Mitisk\Yii2Admin\models\ContentBlock;
use Mitisk\Yii2Admin\models\File;
use Yii;
use yii\base\Component;
use yii\caching\TagDependency;
use yii\db\IntegrityException;

/**
 * Текстовые блоки на сайте (`Yii::$app->blocks`).
 *
 * Все блоки читаются одним запросом и кэшируются до любого изменения
 * (TagDependency). Отсутствующий блок создаётся из значения по умолчанию
 * при первом выводе виджетом. Если таблицы нет (миграции не применены),
 * сервис отдаёт значения по умолчанию и ничего не создаёт.
 */
class ContentBlockService extends Component
{
    /** Создавать отсутствующие блоки при выводе виджетом. */
    public bool $autoCreate = true;

    /** Время жизни кэша, 0 — до изменения блока. */
    public int $cacheDuration = 0;

    /** @var array<string, array{id: int, type: string, value: ?string, name: string, active: bool}>|null */
    private ?array $_blocks = null;

    /** Таблица недоступна в этом запросе. */
    private bool $_broken = false;

    /** @var array<string, array{name: string, type: string}> */
    private array $_used = [];

    /** @var array<int, string|null> */
    private array $_imageUrls = [];

    private ?BlockRenderer $_renderer = null;

    /**
     * Значение блока: строка или DTO, как в {@see BlockValueCodec::decode()}.
     * Нет блока или он выключен — `$default`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->row($key);
        if ($row === null || !$row['active']) {
            return $default;
        }
        return BlockValueCodec::decode(BlockType::tryFrom($row['type']) ?? BlockType::Text, $row['value']);
    }

    public function link(string $key): ?LinkValue
    {
        $value = $this->get($key);
        return $value instanceof LinkValue && !$value->isEmpty() ? $value : null;
    }

    public function imageUrl(string $key): ?string
    {
        $ids = BlockValueCodec::fileIds(BlockType::Image, $this->row($key)['value'] ?? null);
        return $ids === [] ? null : $this->imageUrlById($ids[0]);
    }

    /**
     * URL картинки по id записи `file`; запоминается на время запроса.
     */
    public function imageUrlById(int $id): ?string
    {
        if (!array_key_exists($id, $this->_imageUrls)) {
            $this->_imageUrls[$id] = File::findOne($id)?->getUrl();
        }
        return $this->_imageUrls[$id];
    }

    /**
     * Находит блок или создаёт его из значения по умолчанию.
     *
     * @param array{name?: string, hint?: string, group?: string} $meta
     * @return array{id: int, type: string, value: ?string, name: string, active: bool}|null Null — таблица недоступна.
     * @throws \InvalidArgumentException Ключ не подходит под {@see ContentBlock::KEY_PATTERN}.
     */
    public function ensure(string $key, BlockType $type, mixed $default, array $meta = []): ?array
    {
        if (!ContentBlock::isValidKey($key)) {
            throw new \InvalidArgumentException('Недопустимый ключ блока «' . $key . '»: только [a-z0-9._-].');
        }
        $row = $this->row($key);
        if ($row !== null || $this->_broken || !$this->autoCreate) {
            return $row;
        }

        $block = new ContentBlock([
            'key' => $key,
            'name' => ($meta['name'] ?? '') !== '' ? $meta['name'] : $key,
            'type' => $type->value,
            'value' => BlockValueCodec::encode($type, $default),
            'group' => $meta['group'] ?? '',
            'hint' => ($meta['hint'] ?? '') !== '' ? $meta['hint'] : null,
            'is_active' => 1,
            'from_code' => 1,
        ]);
        try {
            $block->save(false);
        } catch (IntegrityException) {
            // Параллельный запрос успел создать блок: перечитываем из БД
            $block = ContentBlock::find()->byKey($key)->one();
            if ($block === null) {
                return null;
            }
        } catch (\Throwable $e) {
            Yii::warning('ContentBlock: не удалось создать «' . $key . '»: ' . $e->getMessage(), __METHOD__);
            return null;
        }

        return $this->_blocks[$key] = self::toRow($block);
    }

    /**
     * HTML блока без обёртки Admin Bar; запоминает блок как выведенный на странице.
     * Null — блок выключен.
     *
     * @param array $opts Опции {@see BlockRenderer::render()}.
     * @param array{name?: string, hint?: string, group?: string} $meta
     */
    public function renderBlock(string $key, BlockType $type, mixed $default, array $opts = [], array $meta = []): ?string
    {
        $row = $this->ensure($key, $type, $default, $meta);
        if ($row === null) {
            // Таблица недоступна: выводим значение из кода
            return $this->renderer()->render($type, BlockValueCodec::decode($type, BlockValueCodec::encode($type, $default)), $opts);
        }
        $realType = BlockType::tryFrom($row['type']) ?? $type;
        $this->_used[$key] = ['name' => $row['name'], 'type' => $realType->value];
        if (!$row['active']) {
            return null;
        }
        return $this->renderer()->render($realType, BlockValueCodec::decode($realType, $row['value']), $opts);
    }

    /**
     * Блоки, выведенные в этом запросе: key => [name, type].
     *
     * @return array<string, array{name: string, type: string}>
     */
    public function getUsedBlocks(): array
    {
        return $this->_used;
    }

    public function renderer(): BlockRenderer
    {
        return $this->_renderer ??= new BlockRenderer(fn(int $id): ?string => $this->imageUrlById($id));
    }

    /**
     * @return array{id: int, type: string, value: ?string, name: string, active: bool}|null
     */
    private function row(string $key): ?array
    {
        return $this->load()[$key] ?? null;
    }

    /**
     * @return array<string, array{id: int, type: string, value: ?string, name: string, active: bool}>
     */
    private function load(): array
    {
        if ($this->_blocks !== null) {
            return $this->_blocks;
        }
        $query = static function (): array {
            $out = [];
            foreach (ContentBlock::find()->all() as $block) {
                $out[$block->key] = self::toRow($block);
            }
            return $out;
        };
        try {
            $this->_blocks = Yii::$app->has('cache')
                ? Yii::$app->cache->getOrSet(
                    [self::class, 'all'],
                    $query,
                    $this->cacheDuration,
                    new TagDependency(['tags' => ContentBlock::CACHE_TAG])
                )
                : $query();
        } catch (\Throwable $e) {
            Yii::warning('ContentBlock: таблица недоступна: ' . $e->getMessage(), __METHOD__);
            $this->_broken = true;
            $this->_blocks = [];
        }
        return $this->_blocks;
    }

    /**
     * @return array{id: int, type: string, value: ?string, name: string, active: bool}
     */
    private static function toRow(ContentBlock $block): array
    {
        return [
            'id' => (int)$block->id,
            'type' => (string)$block->type,
            'value' => $block->value,
            'name' => (string)$block->name,
            'active' => (bool)$block->is_active,
        ];
    }
}
