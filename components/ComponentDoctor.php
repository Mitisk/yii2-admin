<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminModel;
use Yii;
use yii\db\ActiveRecord;

/**
 * «Проверка компонента»: сверяет настройки раздела админки с его моделью и находит то, из-за чего
 * раздел молча не работает, — поле вне rules() не сохраняется, обязательного поля нет в форме,
 * колонка или источник списка указывают на несуществующее.
 *
 * Проверка ничего не меняет и не делает запросов, кроме чтения схемы таблицы.
 */
final class ComponentDoctor
{
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const OK = 'ok';

    /** Служебные колонки списка, которых нет в модели */
    private const LIST_SERVICE_COLUMNS = ['admin_checkbox', 'admin_number', 'admin_actions'];

    /**
     * @return array<int, array{level: string, title: string, text: string}> сначала ошибки, затем замечания,
     *         затем то, что в порядке
     */
    public static function check(AdminModel $component): array
    {
        $report = [];
        $class = (string) $component->model_class;

        if ($class === '' || !class_exists($class) || !is_subclass_of($class, ActiveRecord::class)) {
            return [self::item(
                self::ERROR,
                'Класс модели не найден',
                $class === ''
                    ? 'Укажите на вкладке «Основные» класс модели, например app\models\Product.'
                    : 'Класс ' . $class . ' не существует или не наследует yii\db\ActiveRecord. Проверьте namespace и имя файла.'
            )];
        }

        /** @var ActiveRecord $model */
        $model = new $class();
        $schema = $class::getTableSchema();
        $columns = $schema !== null ? array_keys($schema->columns) : [];
        $safe = $model->safeAttributes();

        // --- Таблица и ключ ---
        if ($schema === null) {
            $report[] = self::item(self::ERROR, 'Таблицы нет в базе', 'Таблица ' . $class::tableName() . ' не найдена: примените миграцию.');
        } elseif ($schema->primaryKey !== ['id']) {
            $report[] = self::item(self::ERROR, 'Первичный ключ не «id»', 'Универсальный раздел работает только с ключом id: по нему строятся ссылки, файлы и связи.');
        } else {
            $report[] = self::item(self::OK, 'Модель и таблица', $class . ', первичный ключ id.');
        }

        if ((string) $component->alias === '') {
            $report[] = self::item(self::WARNING, 'Нет адреса раздела', 'Заполните «URL-адрес (Slug)» на вкладке «Основные»: без него раздел не открыть.');
        }

        // --- Форма ---
        $fields = self::formFields($component);
        if ($fields === []) {
            $report[] = self::item(self::ERROR, 'Форма пустая', 'На холсте нет ни одного поля: запись нельзя будет ни создать, ни изменить. Перетащите поля или нажмите «Собрать автоматически».');
        }

        $formNames = [];
        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $formNames[] = $name;
            $known = in_array($name, $columns, true) || $model->canSetProperty($name) || $model->canGetProperty($name);

            if (!$known) {
                $report[] = self::item(self::ERROR, 'Поля «' . $name . '» нет в модели', 'Колонки или свойства с таким именем нет. Уберите поле с холста или добавьте публичное свойство в модель.');
                continue;
            }
            if (empty($field['readonly']) && !in_array($name, $safe, true)) {
                $report[] = self::item(self::WARNING, 'Поле «' . $name . '» не сохранится', 'Его нет в rules() модели, а форма сохраняет только атрибуты из правил. Добавьте правило (хотя бы safe) или сделайте поле «только для чтения».');
            }
            foreach (self::selectProblems($field, $class, $model) as $problem) {
                $report[] = $problem;
            }
        }

        // --- Обязательные атрибуты, которых нет в форме ---
        if ($fields !== []) {
            $missing = [];
            foreach ($safe as $attribute) {
                if ($model->isAttributeRequired($attribute) && !in_array($attribute, $formNames, true)) {
                    $missing[] = $attribute;
                }
            }
            if ($missing !== []) {
                $report[] = self::item(self::WARNING, 'В форме нет обязательных полей', 'Атрибуты ' . implode(', ', $missing) . ' обязательны по rules(), но на холст не вынесены: новая запись не сохранится, пока у них нет значения по умолчанию.');
            }
        }

        // --- Таблица списка ---
        $list = self::decode($component->list);
        $enabled = [];
        foreach ($list as $name => $options) {
            if (is_array($options) && filter_var($options['on'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $enabled[] = (string) $name;
            }
        }
        $dataColumns = array_filter($enabled, static fn (string $name): bool => !in_array($name, self::LIST_SERVICE_COLUMNS, true));
        if ($dataColumns === []) {
            $report[] = self::item(self::ERROR, 'В таблице нет колонок', 'На вкладке «Таблица (Grid)» не включена ни одна колонка с данными: список записей будет пустым.');
        }
        foreach ($dataColumns as $name) {
            if (str_starts_with($name, 'admin_link_')) {
                continue;
            }
            if (!in_array($name, $columns, true) && !$model->canGetProperty($name)) {
                $report[] = self::item(self::WARNING, 'Колонка «' . $name . '» ничего не покажет', 'В модели нет такого атрибута. Выключите колонку или добавьте геттер get' . ucfirst($name) . '().');
            }
        }

        // --- Заголовок записи и сортировка ---
        foreach (['admin_label' => 'Основной заголовок записи', 'default_sort_attribute' => 'Сортировка по умолчанию'] as $attribute => $label) {
            $value = (string) $component->{$attribute};
            if ($value !== '' && !in_array($value, $columns, true) && !$model->canGetProperty($value)) {
                $report[] = self::item(self::WARNING, $label . ': нет атрибута «' . $value . '»', 'Выберите на вкладке «Основные» существующий атрибут модели.');
            }
        }

        // --- Ссылки-кнопки ---
        $linkIds = array_column(self::decode($component->links), 'id');
        $used = [];
        foreach ($list as $name => $options) {
            if (is_array($options) && str_starts_with((string) $name, 'admin_link_')) {
                $used = array_merge($used, (array) ($options['items'] ?? []));
            }
        }
        foreach (self::decode($component->data) as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'link' && !empty($item['link_id'])) {
                $used[] = $item['link_id'];
            }
        }
        $lost = array_values(array_unique(array_diff(array_filter($used, 'is_string'), $linkIds)));
        if ($lost !== []) {
            $report[] = self::item(self::WARNING, 'Кнопки без ссылки', 'В таблице или форме стоят кнопки, которых нет в пуле ссылок (' . implode(', ', $lost) . '): они не покажутся. Уберите их или создайте ссылки заново.');
        }

        // --- Права ---
        if (Yii::$app->has('authManager') && Yii::$app->authManager->getPermission($class . '\view') === null) {
            $report[] = self::item(self::WARNING, 'Нет разрешений раздела', 'Разрешения ' . $class . '\view | create | update | delete не созданы: раздел видит только роль admin. Они создаются при сохранении компонента с включённым «Показывать в меню» или на странице ролей.');
        }

        if (self::problemCount($report, self::ERROR) === 0 && self::problemCount($report, self::WARNING) === 0) {
            $report[] = self::item(self::OK, 'Замечаний нет', 'Форма, таблица и права согласованы с моделью.');
        }

        $order = [self::ERROR => 0, self::WARNING => 1, self::OK => 2];
        usort($report, static fn (array $a, array $b): int => $order[$a['level']] <=> $order[$b['level']]);

        return $report;
    }

    /**
     * @param array<int, array{level: string, title: string, text: string}> $report
     */
    public static function problemCount(array $report, string $level): int
    {
        return count(array_filter($report, static fn (array $item): bool => $item['level'] === $level));
    }

    /**
     * Поля холста, привязанные к атрибутам модели (без заголовков, абзацев и кнопок).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function formFields(AdminModel $component): array
    {
        $fields = [];
        foreach (self::decode($component->data) as $item) {
            if (is_array($item) && empty($item['isContent']) && !empty($item['name'])) {
                $fields[] = $item;
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<int, array{level: string, title: string, text: string}>
     */
    private static function selectProblems(array $field, string $class, ActiveRecord $model): array
    {
        if (($field['type'] ?? '') !== 'select') {
            return [];
        }
        $name = (string) $field['name'];
        $source = (string) ($field['selectSourceVal'] ?? '');
        $type = (string) ($field['selectSourceType'] ?? '');
        $title = 'Список «' . $name . '»: нет источника значений';

        if ($source === '') {
            return [self::item(self::ERROR, $title, 'В настройках поля не выбран источник: список будет пустым.')];
        }
        if ($type === 'method') {
            if (!method_exists($class, $source) || !(new \ReflectionMethod($class, $source))->isStatic()) {
                return [self::item(self::ERROR, $title, 'В модели нет публичного статического метода ' . $source . '(), который возвращает [значение => подпись].')];
            }
        } elseif ($type === 'entity') {
            if (!method_exists($model, $source)) {
                return [self::item(self::ERROR, $title, 'В модели нет связи ' . $source . '().')];
            }
            if (!empty($field['selectMultiple']) && strcasecmp('get' . $name, $source) === 0) {
                return [self::item(self::ERROR, 'Список «' . $name . '»: имя совпадает со связью', 'Свойство для множественного выбора не должно называться как связь: заведите, например, public $' . $name . '_ids.')];
            }
        } elseif ($type === 'enum') {
            if (!enum_exists($source)) {
                return [self::item(self::ERROR, $title, 'Перечисление ' . $source . ' не найдено.')];
            }
        }

        return [];
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

    /**
     * @return array{level: string, title: string, text: string}
     */
    private static function item(string $level, string $title, string $text): array
    {
        return ['level' => $level, 'title' => $title, 'text' => $text];
    }
}
