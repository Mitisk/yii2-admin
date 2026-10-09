<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\pages;

/**
 * Шаблоны страниц — файлы `*.php` в папке сайта (обычно `@app/views/page`).
 * Подпись берётся из докблока `@title …`, иначе из имени файла. Файлы с `_` в начале — части, не шаблоны.
 */
final class TemplateFinder
{
    public const DEFAULT = 'default';

    /**
     * @return array<string, string> имя => подпись, отсортировано по имени
     */
    public static function find(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob(rtrim($dir, '/\\') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if ($name === '' || $name[0] === '_') {
                continue;
            }
            $head = (string)file_get_contents($file, false, null, 0, 2048);
            $out[$name] = preg_match('/@title\s+(.+?)\s*(\*\/|$)/mu', $head, $m)
                ? trim($m[1])
                : ucfirst(str_replace(['-', '_'], ' ', $name));
        }
        ksort($out);
        return $out;
    }
}
