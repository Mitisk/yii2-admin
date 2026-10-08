<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\dto;

/**
 * Пункт блока типа «Список». Какие поля использует сайт, задаёт `itemFields` виджета.
 */
final class ListItem
{
    /** Все поля пункта, допустимые в `itemFields`. */
    public const FIELDS = ['title', 'text', 'url', 'image'];

    public function __construct(
        public readonly string $title = '',
        public readonly string $text = '',
        public readonly string $url = '',
        public readonly ?int $image = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data Данные из JSON или формы.
     */
    public static function fromArray(array $data): self
    {
        $str = static fn(string $k): string => trim(is_scalar($data[$k] ?? null) ? (string)$data[$k] : '');
        $image = is_numeric($data['image'] ?? null) ? (int)$data['image'] : 0;
        return new self($str('title'), $str('text'), $str('url'), $image > 0 ? $image : null);
    }

    /**
     * @return array{title: string, text: string, url: string, image: int|null}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'text' => $this->text, 'url' => $this->url, 'image' => $this->image];
    }

    public function isEmpty(): bool
    {
        return $this->title === '' && $this->text === '' && $this->url === '' && $this->image === null;
    }
}
