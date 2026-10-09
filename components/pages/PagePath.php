<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\pages;

use yii\helpers\Inflector;

/**
 * Слаги и адреса страниц: транслитерация, проверка, склейка с адресом родителя,
 * зарезервированные первые сегменты (маршруты, которые страница не должна перекрывать).
 */
final class PagePath
{
    public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,127}$/';

    /** Первые сегменты адреса, занятые модулем и сайтом. */
    public const RESERVED = [
        'admin', 'site', 'assets', 'sitemap.xml', 'robots.txt', 'favicon.ico',
        'page', 'pages', 'sitemap', 'debug', 'gii', 'index.php',
    ];

    public static function slugify(string $title): string
    {
        $slug = Inflector::slug($title, '-', true);
        $slug = trim(preg_replace('/-+/', '-', (string)$slug) ?? '', '-');
        $slug = substr($slug, 0, 128);
        $slug = rtrim($slug, '-');
        return $slug !== '' && preg_match(self::SLUG_PATTERN, $slug) ? $slug : 'page';
    }

    public static function isValidSlug(string $slug): bool
    {
        return (bool)preg_match(self::SLUG_PATTERN, $slug);
    }

    public static function join(?string $parentPath, string $slug): string
    {
        $parent = trim((string)$parentPath, '/');
        return $parent === '' ? $slug : $parent . '/' . $slug;
    }

    /**
     * @param list<string> $extra Дополнительные зарезервированные сегменты (контроллеры и модули сайта).
     */
    public static function isReservedSegment(string $segment, array $extra = []): bool
    {
        $segment = strtolower($segment);
        return in_array($segment, self::RESERVED, true) || in_array($segment, array_map('strtolower', $extra), true);
    }
}
