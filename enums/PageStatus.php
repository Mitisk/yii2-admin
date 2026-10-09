<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\enums;

/**
 * Статус страницы раздела «Контент». Значение хранится в `page.status`.
 * Отложенная публикация — это Published с `published_at` в будущем.
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Published => 'Опубликована',
            self::Archived => 'В архиве',
        };
    }

    /** CSS-класс бейджа в админке и Admin Bar. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-secondary',
            self::Published => 'bg-success',
            self::Archived => 'bg-dark',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }
        return $out;
    }
}
