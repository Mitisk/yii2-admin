<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use yii\helpers\FileHelper;
use yii\web\AssetManager;

/**
 * Надёжная публикация папки ассетов в режиме копирования.
 *
 * Yii копирует папку в web/assets прямо в обычном запросе и считает её готовой, как только она
 * появилась. Если запрос оборвался посреди копирования (лимит времени на хостинге), папка так и
 * остаётся неполной — стилей нет, пока её не удалить руками. Здесь копирование идёт без лимита
 * времени, а в конце в папку кладётся отметка: папку без отметки, брошенную дольше STALE_AFTER
 * секунд назад, считаем оборванной и копируем заново.
 *
 * В режиме ссылок (linkAssets) и при forceCopy ничего не делает: там проблемы нет.
 */
final class AssetPublisher
{
    /** Файл-отметка «папка скопирована целиком» */
    public const MARKER = '.published';

    /** Папка без отметки старше этого — оборванное копирование, секунд */
    public const STALE_AFTER = 600;

    /**
     * @param array<string, mixed> $options publishOptions бандлов этой папки
     * @return bool true — папка скопирована в этом вызове
     */
    public static function ensure(AssetManager $manager, string $source, array $options = []): bool
    {
        if ($manager->linkAssets || $manager->forceCopy) {
            return false;
        }
        $source = rtrim($source, '/\\');
        $path = $manager->getPublishedPath($source);
        if ($path === false || is_file($path . DIRECTORY_SEPARATOR . self::MARKER)) {
            return false;
        }

        if (is_dir($path)) {
            // Недавно начатая папка: её, скорее всего, прямо сейчас копирует соседний запрос
            if ((int) @filemtime($path) > time() - self::STALE_AFTER) {
                return false;
            }
            @set_time_limit(0);
            FileHelper::removeDirectory($path);
        }

        @set_time_limit(0);
        ignore_user_abort(true);
        $manager->publish($source, $options);
        // Свой менеджер мог опубликовать ссылкой: отметка тогда попала бы в исходники пакета
        if (realpath($path) === realpath($source)) {
            return false;
        }
        @file_put_contents($path . DIRECTORY_SEPARATOR . self::MARKER, (string) time());

        return true;
    }
}
