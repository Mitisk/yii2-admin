<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\dto;

/**
 * Значение блока типа «Ссылка».
 */
final class LinkValue
{
    public function __construct(
        public readonly string $text,
        public readonly string $url,
        public readonly string $target = '_self',
    ) {
    }

    /**
     * @param array<string, mixed> $data Данные из JSON или формы.
     */
    public static function fromArray(array $data): self
    {
        $target = (string)($data['target'] ?? '_self');
        return new self(
            trim(is_scalar($data['text'] ?? null) ? (string)$data['text'] : ''),
            trim(is_scalar($data['url'] ?? null) ? (string)$data['url'] : ''),
            $target === '_blank' ? '_blank' : '_self',
        );
    }

    /**
     * @return array{text: string, url: string, target: string}
     */
    public function toArray(): array
    {
        return ['text' => $this->text, 'url' => $this->url, 'target' => $this->target];
    }

    public function isEmpty(): bool
    {
        return $this->url === '';
    }

    /**
     * Безопасен ли URL для href: относительный путь, якорь, query, http(s), mailto, tel.
     *
     * Протокол-относительные `//host` запрещены: ими легко увести на чужой домен.
     */
    public static function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }
        if (in_array($url[0], ['/', '#', '?'], true)) {
            return true;
        }
        return (bool)preg_match('~^(https?://|mailto:|tel:)~i', $url);
    }
}
