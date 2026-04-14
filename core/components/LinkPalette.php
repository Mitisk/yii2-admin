<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\core\components;

/**
 * Статические справочники для пользовательских ссылок-кнопок:
 * whitelist иконок и пастельная палитра цветов.
 */
final class LinkPalette
{
    /**
     * Разрешённые классы иконок (whitelist для предотвращения XSS).
     *
     * @return array<int, string>
     */
    public static function icons(): array
    {
        return [
            'icon-eye', 'icon-edit-3', 'icon-trash-2', 'icon-plus', 'icon-search',
            'icon-settings', 'icon-info', 'icon-copy', 'icon-sliders', 'icon-link',
            'icon-external-link', 'icon-download', 'icon-upload', 'icon-share-2',
            'icon-mail', 'icon-phone', 'icon-file', 'icon-file-text', 'icon-folder',
            'icon-calendar', 'icon-clock', 'icon-check', 'icon-x', 'icon-star',
            'icon-heart', 'icon-flag', 'icon-bell', 'icon-user', 'icon-users',
            'icon-lock', 'icon-unlock', 'icon-refresh-ccw', 'icon-printer',
            'icon-arrow-right', 'icon-arrow-left', 'icon-chevron-right',
            'icon-chevron-left', 'icon-home', 'icon-filter', 'icon-play',
        ];
    }

    /**
     * Пастельная палитра (ключ → hex).
     *
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return [
            'pastel-blue'   => '#dbeafe',
            'pastel-green'  => '#dcfce7',
            'pastel-pink'   => '#fce7f3',
            'pastel-yellow' => '#fef9c3',
            'pastel-purple' => '#ede9fe',
            'pastel-orange' => '#ffedd5',
            'pastel-cyan'   => '#cffafe',
            'pastel-red'    => '#fee2e2',
            'pastel-gray'   => '#e5e7eb',
        ];
    }

    /**
     * Разрешённые режимы открытия ссылки.
     *
     * @return array<string, string>
     */
    public static function targets(): array
    {
        return [
            '_blank' => 'В новой вкладке',
            '_self'  => 'В этой же вкладке',
            'ajax'   => 'Ajax-запрос',
        ];
    }

    public static function isValidIcon(?string $icon): bool
    {
        return $icon !== null && $icon !== '' && in_array($icon, self::icons(), true);
    }

    public static function isValidColor(?string $color): bool
    {
        return $color !== null && $color !== '' && isset(self::colors()[$color]);
    }

    public static function isValidTarget(?string $target): bool
    {
        return $target !== null && isset(self::targets()[$target]);
    }
}
