<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\content;

use Mitisk\Yii2Admin\dto\ImageValue;
use Mitisk\Yii2Admin\dto\LinkValue;
use Mitisk\Yii2Admin\enums\BlockType;

/**
 * Преобразование `content_block.value` между строкой БД и типизированным значением.
 *
 * | Тип   | В БД                                           |
 * |-------|------------------------------------------------|
 * | Text  | строка                                         |
 * | Html  | очищенный HTML                                 |
 * | Image | {"file_id": 12, "alt": "…", "title": "…"}      |
 * | Link  | {"text": "…", "url": "…", "target": "_self"}   |
 *
 * Повреждённый JSON никогда не бросает исключение: значение считается пустым,
 * чтобы сломанная запись не роняла страницу сайта.
 */
final class BlockValueCodec
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public static function decode(BlockType $type, ?string $raw): string|LinkValue|ImageValue
    {
        return match ($type) {
            BlockType::Text, BlockType::Html => (string)$raw,
            BlockType::Link => LinkValue::fromArray(self::json($raw)),
            BlockType::Image => ImageValue::fromArray(self::json($raw)),
        };
    }

    /**
     * @param mixed $value Строка, DTO или сырой массив (значение по умолчанию из кода).
     */
    public static function encode(BlockType $type, mixed $value): string
    {
        return match ($type) {
            BlockType::Text, BlockType::Html => is_scalar($value) ? (string)$value : '',
            BlockType::Link => json_encode(
                ($value instanceof LinkValue ? $value : LinkValue::fromArray(is_array($value) ? $value : []))->toArray(),
                self::JSON_FLAGS
            ),
            BlockType::Image => json_encode(
                ($value instanceof ImageValue ? $value : ImageValue::fromArray(is_array($value) ? $value : []))->toArray(),
                self::JSON_FLAGS
            ),
        };
    }

    /**
     * Id файлов, на которые ссылается значение (для удаления осиротевших картинок).
     *
     * @return list<int>
     */
    public static function fileIds(BlockType $type, ?string $raw): array
    {
        $value = self::decode($type, $raw);
        return $value instanceof ImageValue && $value->fileId !== null ? [$value->fileId] : [];
    }

    /**
     * @return array<mixed>
     */
    private static function json(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        return is_array($data) ? $data : [];
    }
}
