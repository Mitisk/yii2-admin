<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\audit;

use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\AuditLog;
use Mitisk\Yii2Admin\models\ContentBlock;
use Mitisk\Yii2Admin\models\Page;
use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\StringHelper;

/**
 * Запись журнала действий человеческим языком: раздел вместо имени класса, подписи полей
 * вместо имён колонок, даты и флажки вместо чисел.
 *
 * Раздел и ссылка на запись ищутся так: компонент админки с этим классом модели → компонент
 * с той же таблицей (проект пишет в журнал свою модель, а в админке раздел заведён на её
 * подкласс) → встроенные модели пакета → короткое имя класса.
 */
final class AuditPresenter
{
    /** Длиннее — значение в журнале сворачивается под «показать полностью». */
    public const LONG_VALUE = 160;

    /** Поля, значения которых в журнале не показываются (и с этой версии не пишутся). */
    private const SENSITIVE = '/pass(word|wd)?|secret|token|auth_?key|api_?key|_hash$|^hash$/i';

    /** @var array<class-string, array{name: string, url: string|null}> */
    private const BUILT_IN = [
        ContentBlock::class => ['name' => 'Текстовые блоки', 'url' => '/admin/content-block/update/?id='],
        Page::class => ['name' => 'Страницы', 'url' => '/admin/page/update/?id='],
        AdminUser::class => ['name' => 'Пользователи', 'url' => '/admin/user/view/?id='],
        AuditLog::class => ['name' => 'Журнал действий', 'url' => null],
    ];

    /** @var array<string, array<string, mixed>>|null компоненты по классу модели */
    private ?array $byClass = null;

    /** @var array<string, array<string, mixed>> компоненты по имени таблицы */
    private array $byTable = [];

    /** @var array<string, array<string, mixed>|null> */
    private array $resolved = [];

    /** @var array<string, array<string, string>> */
    private array $modelLabels = [];

    /** @var array<string, array<int|string, string>> */
    private array $options = [];

    /** @var array<int, string> */
    private array $userNames = [];

    public static function isSensitive(string $attribute): bool
    {
        return preg_match(self::SENSITIVE, $attribute) === 1;
    }

    /** Название раздела: «Товары», «Текстовые блоки»; не нашли — короткое имя класса. */
    public function section(string $modelClass): string
    {
        $component = $this->component($modelClass);
        if ($component !== null) {
            return (string) $component['name'];
        }

        return self::BUILT_IN[$modelClass]['name'] ?? StringHelper::basename($modelClass);
    }

    /**
     * @param string[] $modelClasses
     * @return array<string, string> класс => название раздела, по алфавиту
     */
    public function sections(array $modelClasses): array
    {
        $sections = [];
        foreach ($modelClasses as $class) {
            $sections[(string) $class] = $this->section((string) $class);
        }
        asort($sections, SORT_LOCALE_STRING);

        return $sections;
    }

    public function label(AuditLog $log): string
    {
        $label = trim((string) $log->model_label);

        return $label !== '' ? $label : 'ID: ' . $log->model_id;
    }

    /** Ссылка на запись в админке; у удалённой записи и у записи без раздела её нет. */
    public function url(AuditLog $log): ?string
    {
        if ($log->action === 'delete' || $log->action === AuditActions::CLEAR) {
            return null;
        }
        $id = rawurlencode((string) $log->model_id);
        if ($log->component_alias) {
            return '/admin/' . $log->component_alias . '/view/?id=' . $id;
        }
        $component = $this->component($log->model_class);
        if ($component !== null) {
            return '/admin/' . $component['alias'] . '/view/?id=' . $id;
        }
        $url = self::BUILT_IN[$log->model_class]['url'] ?? null;

        return $url !== null ? $url . $id : null;
    }

    /**
     * Изменения записи для показа.
     *
     * @return array<int, array{attribute: string, field: string, old: array{text: string, empty: bool}, new: array{text: string, empty: bool}}>
     */
    public function changes(AuditLog $log): array
    {
        $rows = [];
        foreach ($log->getDiffArray() as $attribute => $pair) {
            $attribute = (string) $attribute;
            $pair = is_array($pair) ? array_values($pair) : [null, $pair];
            $rows[] = [
                'attribute' => $attribute,
                'field' => $this->fieldLabel($log->model_class, $attribute),
                'old' => $this->value($log->model_class, $attribute, $pair[0] ?? null),
                'new' => $this->value($log->model_class, $attribute, $pair[1] ?? null),
            ];
        }

        return $rows;
    }

    /** «Chrome, Windows» из user agent; не распознали — пустая строка. */
    public function client(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;
        if ($userAgent === '') {
            return '';
        }
        $browsers = [
            'YaBrowser' => 'Яндекс Браузер', 'Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox',
            'Chrome' => 'Chrome', 'Safari' => 'Safari',
        ];
        $systems = [
            'Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows',
            'Mac OS X' => 'macOS', 'Linux' => 'Linux',
        ];
        $parts = [];
        foreach ([$browsers, $systems] as $map) {
            foreach ($map as $needle => $name) {
                if (str_contains($userAgent, $needle)) {
                    $parts[] = $name;
                    break;
                }
            }
        }

        return implode(', ', $parts);
    }

    public function fieldLabel(string $modelClass, string $attribute): string
    {
        $component = $this->component($modelClass);
        if ($component !== null) {
            $label = $component['labels'][$attribute] ?? $component['fields'][$attribute]['label'] ?? null;
            if (is_string($label) && trim($label) !== '') {
                return trim($label);
            }
        }

        return $this->modelLabels($modelClass)[$attribute] ?? $attribute;
    }

    /**
     * @return array{text: string, empty: bool}
     */
    public function value(string $modelClass, string $attribute, mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return ['text' => '', 'empty' => true];
        }
        if (self::isSensitive($attribute)) {
            return ['text' => 'скрыто', 'empty' => false];
        }
        if (is_array($raw) || is_object($raw)) {
            $json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            return ['text' => $json === false ? '' : $json, 'empty' => false];
        }
        if (is_bool($raw)) {
            return ['text' => $raw ? 'Да' : 'Нет', 'empty' => false];
        }

        $field = $this->component($modelClass)['fields'][$attribute] ?? [];
        $type = (string) ($field['type'] ?? '');
        $text = (string) $raw;

        if ($type === 'posted' && in_array($text, ['0', '1'], true)) {
            return ['text' => $text === '1' ? 'Да' : 'Нет', 'empty' => false];
        }
        if ($type === 'select') {
            $options = $this->selectOptions($modelClass, $attribute, $field);
            if (isset($options[$text])) {
                return ['text' => $options[$text], 'empty' => false];
            }
        }
        // Поле «пользователь» в форме раздела или колонка «кем»: created_by, admin_hidden_by
        if (($type === 'user' || str_ends_with($attribute, '_by')) && ctype_digit($text)) {
            $name = $this->userName((int) $text);

            return ['text' => $name !== null ? $name . ' (#' . $text . ')' : '#' . $text, 'empty' => false];
        }
        if ($this->looksLikeTimestamp($attribute, $type, $text)) {
            $withTime = $type !== 'date' || !empty($field['withTime']);

            return ['text' => date($withTime ? 'd.m.Y H:i' : 'd.m.Y', (int) $text), 'empty' => false];
        }

        return ['text' => $text, 'empty' => false];
    }

    /** Поле с датой: тип «дата» в форме раздела или имя на _at / _date / _time и значение — unix-время. */
    private function looksLikeTimestamp(string $attribute, string $type, string $text): bool
    {
        if (!ctype_digit($text) || strlen($text) < 9 || strlen($text) > 10) {
            return false;
        }

        return $type === 'date' || preg_match('/(_at|_date|_time|^date|^time)$/', $attribute) === 1;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<int|string, string>
     */
    private function selectOptions(string $modelClass, string $attribute, array $field): array
    {
        $key = $modelClass . '::' . $attribute;
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        $options = [];
        $source = (string) ($field['selectSourceVal'] ?? '');
        try {
            if (($field['selectSourceType'] ?? '') === 'method' && $source !== '' && method_exists($modelClass, $source)) {
                $method = new \ReflectionMethod($modelClass, $source);
                if ($method->isStatic() && $method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
                    $list = $method->invoke(null);
                    foreach (is_array($list) ? $list : [] as $value => $label) {
                        if (is_scalar($label)) {
                            $options[(string) $value] = (string) $label;
                        }
                    }
                }
            } elseif (($field['selectSourceType'] ?? '') === 'enum' && enum_exists($source) && is_subclass_of($source, \BackedEnum::class)) {
                foreach ($source::cases() as $case) {
                    $options[(string) $case->value] = method_exists($case, 'label') ? (string) $case->label() : $case->name;
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('Журнал действий: список значений ' . $key . ' недоступен — ' . $e->getMessage(), __METHOD__);
        }

        return $this->options[$key] = $options;
    }

    private function userName(int $id): ?string
    {
        if (!array_key_exists($id, $this->userNames)) {
            $row = (new Query())->select(['name', 'username'])->from(AdminUser::tableName())->where(['id' => $id])->one();
            $name = is_array($row) ? trim((string) ($row['name'] ?: $row['username'])) : '';
            $this->userNames[$id] = $name;
        }

        return $this->userNames[$id] !== '' ? $this->userNames[$id] : null;
    }

    /**
     * @return array<string, string>
     */
    private function modelLabels(string $modelClass): array
    {
        if (isset($this->modelLabels[$modelClass])) {
            return $this->modelLabels[$modelClass];
        }
        $labels = [];
        try {
            if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
                $reflection = new \ReflectionClass($modelClass);
                if ($reflection->isInstantiable()) {
                    /** @var Model $model */
                    $model = $reflection->newInstanceWithoutConstructor();
                    foreach ($model->attributeLabels() as $attribute => $label) {
                        if (is_string($label) && $label !== '') {
                            $labels[(string) $attribute] = $label;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('Журнал действий: подписи полей ' . $modelClass . ' недоступны — ' . $e->getMessage(), __METHOD__);
        }

        return $this->modelLabels[$modelClass] = $labels;
    }

    /**
     * Компонент админки для класса модели: по классу, иначе по таблице.
     *
     * @return array{name: string, alias: string, labels: array<string, mixed>, fields: array<string, array<string, mixed>>}|null
     */
    private function component(string $modelClass): ?array
    {
        if (array_key_exists($modelClass, $this->resolved)) {
            return $this->resolved[$modelClass];
        }
        $this->loadComponents();

        $component = $this->byClass[$modelClass] ?? null;
        if ($component === null) {
            $table = $this->tableOf($modelClass);
            $component = $table !== null ? ($this->byTable[$table] ?? null) : null;
        }

        return $this->resolved[$modelClass] = $component;
    }

    private function tableOf(string $modelClass): ?string
    {
        try {
            if (!class_exists($modelClass) || !is_subclass_of($modelClass, ActiveRecord::class)) {
                return null;
            }
            $db = $modelClass::getDb();
            $table = $db->getSchema()->getRawTableName($modelClass::tableName());
            $prefix = (string) $db->tablePrefix;
            if ($prefix !== '' && str_starts_with($table, $prefix)) {
                $table = substr($table, strlen($prefix));
            }

            return $table;
        } catch (\Throwable) {
            return null;
        }
    }

    private function loadComponents(): void
    {
        if ($this->byClass !== null) {
            return;
        }
        $this->byClass = [];
        $rows = (new Query())
            ->select(['name', 'alias', 'table_name', 'model_class', 'attribute_labels', 'data'])
            ->from(AdminModel::tableName())
            ->where(['view' => 1])
            ->andWhere(['not', ['alias' => null]])
            ->andWhere(['<>', 'alias', ''])
            // Строки без класса модели — заготовки, которые редактор компонентов завёл для таблиц сам
            ->andWhere(['not', ['model_class' => null]])
            ->andWhere(['<>', 'model_class', ''])
            ->all();

        foreach ($rows as $row) {
            $fields = [];
            $data = json_decode((string) $row['data'], true);
            foreach (is_array($data) ? $data : [] as $field) {
                if (is_array($field) && isset($field['name']) && empty($field['isContent'])) {
                    $fields[(string) $field['name']] = $field;
                }
            }
            $labels = json_decode((string) $row['attribute_labels'], true);
            $component = [
                'name' => (string) $row['name'],
                'alias' => (string) $row['alias'],
                'labels' => is_array($labels) ? $labels : [],
                'fields' => $fields,
            ];
            if ((string) $row['model_class'] !== '') {
                $this->byClass[ltrim((string) $row['model_class'], '\\')] = $component;
            }
            if ((string) $row['table_name'] !== '') {
                $this->byTable[(string) $row['table_name']] = $component;
            }
        }
    }
}
