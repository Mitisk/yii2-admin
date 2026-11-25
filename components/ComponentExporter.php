<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminModel;

/**
 * Экспорт настроек компонента в код миграции: раздел настраивают мышкой в редакторе, а на другие
 * окружения переносят миграцией через AdminRegistry::registerComponent().
 */
final class ComponentExporter
{
    /** Метка «вставить как код PHP» (константа, ::class): нулевой байт в настройках из JSON не встречается */
    private const RAW = "\0php:";

    /**
     * Готовый файл миграции для компонента в его сохранённом состоянии.
     */
    public static function migration(AdminModel $component, ?int $time = null): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) ($component->alias ?: $component->table_name))), '_') ?: 'component';
        $class = 'm' . date('ymd_His', $time ?? time()) . '_register_' . $slug . '_admin_component';

        $config = [
            'table_name' => (string) $component->table_name,
            'model_class' => (string) $component->model_class === ''
                ? ''
                : self::RAW . '\\' . ltrim((string) $component->model_class, '\\') . '::class',
            'name' => (string) $component->name,
            'alias' => (string) $component->alias,
        ];
        if ((string) $component->admin_label !== '') {
            $config['admin_label'] = (string) $component->admin_label;
        }
        $config['can_create'] = (bool) $component->can_create;
        $config['in_menu'] = (bool) $component->in_menu;
        if ($component->non_encode) {
            $config['non_encode'] = true;
        }
        if ((string) $component->default_sort_attribute !== '') {
            $config['default_sort_attribute'] = (string) $component->default_sort_attribute;
            $config['default_sort_direction'] = (int) $component->default_sort_direction === SORT_ASC
                ? self::RAW . 'SORT_ASC'
                : self::RAW . 'SORT_DESC';
        }
        if ((string) $component->file_path !== '') {
            $config['file_path'] = (string) $component->file_path;
        }
        foreach (['attribute_labels', 'links', 'list', 'data'] as $key) {
            $value = self::decode($component->{$key});
            if ($value !== []) {
                $config[$key] = $value;
            }
        }

        $table = self::scalar((string) $component->table_name);
        $name = (string) $component->name;

        return "<?php\n\ndeclare(strict_types=1);\n\n"
            . "use Mitisk\\Yii2Admin\\components\\AdminRegistry;\nuse yii\\db\\Migration;\n\n"
            . "/**\n * Раздел админки «" . str_replace('*/', '* /', $name) . "»: настройки выгружены из редактора компонентов.\n */\n"
            . "final class {$class} extends Migration\n{\n"
            . "    public function safeUp(): void\n    {\n"
            . '        AdminRegistry::registerComponent(' . self::export($config, 2) . ");\n"
            . "    }\n\n"
            . "    public function safeDown(): void\n    {\n"
            . "        AdminRegistry::unregisterComponent({$table});\n"
            . "    }\n}\n";
    }

    /**
     * Значение как код PHP: короткие массивы, одна пара на строку, списки без ключей.
     *
     * @param mixed $value
     */
    private static function export($value, int $level): string
    {
        if (is_string($value) && str_starts_with($value, self::RAW)) {
            return substr($value, strlen(self::RAW));
        }
        if (!is_array($value)) {
            return self::scalar($value);
        }
        if ($value === []) {
            return '[]';
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        $flat = self::flat($value, $isList);
        if ($flat !== null && strlen($flat) <= 110 - $level * 4) {
            return $flat;
        }

        $indent = str_repeat('    ', $level + 1);
        $lines = [];
        foreach ($value as $key => $item) {
            $lines[] = $indent . ($isList ? '' : self::scalar($key) . ' => ') . self::export($item, $level + 1) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $level) . ']';
    }

    /**
     * Массив в одну строку, если в нём нет вложенных непустых массивов.
     *
     * @param array<int|string, mixed> $value
     */
    private static function flat(array $value, bool $isList): ?string
    {
        $parts = [];
        foreach ($value as $key => $item) {
            if (is_array($item) && $item !== []) {
                return null;
            }
            $parts[] = ($isList ? '' : self::scalar($key) . ' => ') . self::export($item, 0);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param mixed $value
     */
    private static function scalar($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value) . "'";
    }

    /**
     * @param mixed $value JSON-строка или уже массив
     * @return array<int|string, mixed>
     */
    private static function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
