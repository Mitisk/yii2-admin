<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\fields\FieldsHelper;
use yii\base\Model;
use yii\db\ColumnSchema;
use yii\db\TableSchema;
use yii\helpers\Inflector;

/**
 * Выводит рекомендуемую конфигурацию полей визуального холста из схемы
 * таблицы, экземпляра модели и списка публичных методов-источников.
 *
 * Чистый сервис без состояния и побочных эффектов: используется и для
 * авто-сборки формы, и для «умного drop» на странице
 * /admin/components/update.
 */
final class FieldInferenceService
{
    /** Служебные колонки: группа «Служебное», readonly. */
    private const SERVICE_COLUMNS = ['created_at', 'updated_at', 'deleted_at'];

    /** Типы колонок схемы, считающиеся числовыми. */
    private const NUMERIC_TYPES = [
        'integer', 'bigint', 'smallint', 'tinyint',
        'decimal', 'float', 'double', 'money',
    ];

    /** Типы полей холста, занимающие всю ширину строки. */
    private const FULL_WIDTH_FIELD_TYPES = [
        'textarea', 'html', 'visual', 'json', 'file', 'image', 'url',
    ];

    /** Типы полей холста шириной в полстроки. */
    private const HALF_WIDTH_FIELD_TYPES = [
        'date', 'number', 'select', 'user', 'email', 'phone', 'slug', 'icon',
    ];

    /**
     * @param TableSchema|null     $tableSchema         Схема таблицы компонента
     * @param Model|null           $modelInstance       Экземпляр модели (для attributeLabels)
     * @param array<string,string> $publicStaticMethods Метод => подпись (источники select)
     */
    public function __construct(
        private readonly ?TableSchema $tableSchema,
        private readonly ?Model $modelInstance = null,
        private readonly array $publicStaticMethods = [],
    ) {
    }

    /**
     * Возвращает suggest-конфиг для каждого атрибута.
     *
     * @param string[] $columnNames     Имена колонок/атрибутов
     * @param string[] $requiredColumns Обязательные атрибуты (из rules())
     *
     * @return array<string, array<string, mixed>>
     */
    public function suggestAll(array $columnNames, array $requiredColumns = []): array
    {
        $result = [];
        foreach ($columnNames as $name) {
            $result[$name] = $this->suggestOne(
                $name,
                in_array($name, $requiredColumns, true)
            );
        }

        // Пара title|name + slug — в одну строку 50/50
        foreach (['title', 'name'] as $titleKey) {
            if (isset($result[$titleKey], $result['slug'])) {
                $result[$titleKey]['width'] = '50';
                $result['slug']['width'] = '50';
                break;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function suggestOne(string $name, bool $required): array
    {
        $column = $this->tableSchema?->columns[$name] ?? null;

        $suggest = [
            'type'     => $this->inferType($name, $column),
            'label'    => $this->inferLabel($name, $column),
            'width'    => '100',
            'required' => $required,
            'readonly' => false,
            'withTime' => false,
            'group'    => $this->inferGroup($name),
        ];

        if ($suggest['type'] === 'date' && $column !== null) {
            $suggest['withTime'] = in_array(
                $column->type,
                ['datetime', 'timestamp'],
                true
            );
        }

        $refTable = $this->findForeignKeyTable($name);
        if ($refTable !== null) {
            $method = $this->matchSourceMethod($refTable);
            $suggest['type']             = 'select';
            $suggest['selectSourceType'] = 'entity';
            $suggest['selectSourceVal']  = $method ?? '';
            $suggest['sourceMissing']    = $method === null;
        }

        $suggest['width'] = $this->inferWidth($suggest['type'], $column);

        if ($suggest['group'] === 'service') {
            $suggest['readonly'] = true;
            $suggest['required'] = false;
            $suggest['width']    = '50';
        }

        return $suggest;
    }

    /**
     * Тип поля холста: эвристика по имени приоритетнее, затем тип колонки.
     */
    private function inferType(string $name, ?ColumnSchema $column): string
    {
        $byName = FieldsHelper::getFieldsTypeByName($name);
        if ($byName !== 'text') {
            return $byName;
        }
        if ($column === null) {
            return 'text';
        }
        if ($column->type === 'boolean'
            || ($column->dbType !== null
                && stripos($column->dbType, 'tinyint(1)') === 0)
        ) {
            return 'posted';
        }
        if ($column->type === 'json') {
            return 'json';
        }
        if (in_array($column->type, ['date', 'datetime', 'timestamp', 'time'], true)) {
            return 'date';
        }
        if ($column->type === 'text') {
            return 'html';
        }
        if (in_array($column->type, self::NUMERIC_TYPES, true)) {
            return 'number';
        }

        return 'text';
    }

    /**
     * Лейбл: attributeLabels() → комментарий колонки → humanize имени.
     */
    private function inferLabel(string $name, ?ColumnSchema $column): string
    {
        if ($this->modelInstance !== null) {
            $labels = $this->modelInstance->attributeLabels();
            if (isset($labels[$name]) && $labels[$name] !== '') {
                return (string) $labels[$name];
            }
        }
        if ($column !== null && $column->comment !== null && $column->comment !== '') {
            return $column->comment;
        }

        return Inflector::camel2words(Inflector::id2camel($name, '_'));
    }

    private function inferGroup(string $name): ?string
    {
        if (in_array($name, self::SERVICE_COLUMNS, true)) {
            return 'service';
        }
        if (preg_match('/^(seo_|meta_|og_)/', $name) === 1) {
            return 'seo';
        }

        return null;
    }

    private function inferWidth(string $type, ?ColumnSchema $column): string
    {
        if ($type === 'posted') {
            return '25';
        }
        if (in_array($type, self::HALF_WIDTH_FIELD_TYPES, true)) {
            return '50';
        }
        if (in_array($type, self::FULL_WIDTH_FIELD_TYPES, true)) {
            return '100';
        }
        if ($column !== null && $column->size !== null && $column->size <= 100) {
            return '50';
        }

        return '100';
    }

    /**
     * Имя таблицы, на которую ссылается FK данной колонки, либо null.
     */
    private function findForeignKeyTable(string $name): ?string
    {
        foreach ($this->tableSchema?->foreignKeys ?? [] as $fk) {
            // Формат Yii2: [0 => 'ref_table', 'fk_column' => 'ref_column', ...]
            $refTable = $fk[0] ?? null;
            unset($fk[0]);
            if ($refTable !== null && array_key_exists($name, $fk)) {
                return (string) $refTable;
            }
        }

        return null;
    }

    /**
     * Подбирает метод-источник select по имени таблицы FK.
     * Только сопоставление имён — никаких вызовов методов модели.
     */
    private function matchSourceMethod(string $refTable): ?string
    {
        if ($this->publicStaticMethods === []) {
            return null;
        }

        $base = Inflector::id2camel($refTable, '_');
        $keysLower = [];
        foreach (array_keys($this->publicStaticMethods) as $method) {
            $keysLower[strtolower((string) $method)] = (string) $method;
        }

        $candidates = [
            'get' . $base,
            'get' . Inflector::pluralize($base),
            'get' . $base . 'List',
            'get' . Inflector::singularize($base),
        ];
        foreach ($candidates as $candidate) {
            $lower = strtolower($candidate);
            if (isset($keysLower[$lower])) {
                return $keysLower[$lower];
            }
        }

        // Fallback: первый метод, содержащий базовое имя таблицы
        $needle = strtolower(Inflector::singularize($base));
        foreach ($keysLower as $lower => $method) {
            if (str_contains($lower, $needle)) {
                return $method;
            }
        }

        return null;
    }
}
