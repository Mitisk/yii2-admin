<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\dto;

/**
 * Значение блока типа «Картинка»: ссылка на запись таблицы `file` и подписи.
 */
final class ImageValue
{
    public function __construct(
        public readonly ?int $fileId,
        public readonly string $alt = '',
        public readonly string $title = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data Данные из JSON.
     */
    public static function fromArray(array $data): self
    {
        $id = is_numeric($data['file_id'] ?? null) ? (int)$data['file_id'] : 0;
        return new self(
            $id > 0 ? $id : null,
            trim(is_scalar($data['alt'] ?? null) ? (string)$data['alt'] : ''),
            trim(is_scalar($data['title'] ?? null) ? (string)$data['title'] : ''),
        );
    }

    /**
     * @return array{file_id: int|null, alt: string, title: string}
     */
    public function toArray(): array
    {
        return ['file_id' => $this->fileId, 'alt' => $this->alt, 'title' => $this->title];
    }
}
