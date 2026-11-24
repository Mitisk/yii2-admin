<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminControllerMap;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\Menu;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\StringHelper;

/**
 * Регистрация сущностей проекта в админке из миграций: компоненты, свои контроллеры, меню,
 * виджеты дашборда, RBAC-разрешения компонента.
 *
 * Работает в консоли с минимальным набором компонентов (db и authManager): не обращается к user,
 * session, settings и cache. Все методы идемпотентны, при неудаче бросают \RuntimeException
 * с ошибками AR.
 *
 * ```php
 * AdminRegistry::registerComponent([
 *     'table_name' => 'product', 'model_class' => Product::class, 'name' => 'Товары', 'alias' => 'products',
 *     'list' => [...], 'data' => [...],
 * ]);
 * AdminRegistry::upsertMenuGroup('Каталог', 'fas fa-box', [['text' => 'Товары', 'href' => '/admin/products/']]);
 * ```
 */
final class AdminRegistry
{
    /** Строка таблицы menu с меню слева. */
    public const MENU_ALIAS = 'admin';

    /** Право пунктов меню по умолчанию. */
    public const DEFAULT_RULE = 'admin';

    private const PERMISSION_TITLES = [
        'view' => 'Просмотр',
        'create' => 'Создание',
        'update' => 'Изменение',
        'delete' => 'Удаление',
    ];

    /**
     * Регистрирует или обновляет (по table_name) компонент админки.
     *
     * Обязательные ключи: table_name, model_class, name, alias. Необязательные: admin_label,
     * can_create (bool), list, data, links, attribute_labels (массивы), default_sort_attribute,
     * default_sort_direction, file_path, non_encode (bool), in_menu (bool, по умолчанию false —
     * пункт меню и разрешения роли manager при true создаёт сам AdminModel).
     *
     * @param array<string, mixed> $config
     * @throws \RuntimeException
     */
    public static function registerComponent(array $config): AdminModel
    {
        foreach (['table_name', 'model_class', 'name', 'alias'] as $key) {
            if (empty($config[$key])) {
                throw new \RuntimeException("AdminRegistry: у компонента не задан {$key}.");
            }
        }

        // /admin/components/ сам создаёт «пустые» записи для всех таблиц — ищем по table_name
        $component = AdminModel::findOne(['table_name' => $config['table_name']]) ?? new AdminModel();
        $component->setAttributes([
            'table_name' => $config['table_name'],
            'model_class' => $config['model_class'],
            'name' => $config['name'],
            'alias' => $config['alias'],
            'admin_label' => $config['admin_label'] ?? null,
            'can_create' => !empty($config['can_create']) ? 1 : 0,
            'list' => $config['list'] ?? [],
            'data' => $config['data'] ?? [],
            'links' => $config['links'] ?? [],
            'attribute_labels' => $config['attribute_labels'] ?? [],
            'default_sort_attribute' => $config['default_sort_attribute'] ?? null,
            'default_sort_direction' => $config['default_sort_direction'] ?? null,
            'file_path' => $config['file_path'] ?? null,
            'non_encode' => !empty($config['non_encode']) ? 1 : 0,
            'in_menu' => !empty($config['in_menu']) ? 1 : 0,
            'view' => 1,
        ], false);

        self::saveOrFail($component);

        return $component;
    }

    /** Удаляет строку admin_model: при следующем открытии /admin/components/ модуль создаст пустую. */
    public static function unregisterComponent(string $tableName): void
    {
        AdminModel::deleteAll(['table_name' => $tableName]);
    }

    /**
     * Подключает свой контроллер админки (admin_controller_map), идемпотентно по controller_id.
     *
     * @param array<string, mixed>|null $config свойства контроллера, например ['access' => 'viewReports']
     */
    public static function registerController(string $controllerId, string $class, ?array $config = null): void
    {
        $row = AdminControllerMap::findOne(['controller_id' => $controllerId]) ?? new AdminControllerMap();
        $row->controller_id = $controllerId;
        $row->class = $class;
        $row->config = $config === null ? null : json_encode($config, JSON_UNESCAPED_UNICODE);
        $row->enabled = 1;
        self::saveOrFail($row);
    }

    public static function unregisterController(string $controllerId): void
    {
        AdminControllerMap::deleteAll(['controller_id' => $controllerId]);
    }

    /**
     * Создаёт или дополняет корневую группу меню (href '#'). Дети сливаются по href:
     * совпавший заменяется, новые добавляются в конец.
     *
     * @param array<int, array<string, mixed>> $children каждый — минимум ['text' => ..., 'href' => ...]
     */
    public static function upsertMenuGroup(string $text, string $icon, array $children, string $rule = self::DEFAULT_RULE): void
    {
        $items = self::readMenu();
        $index = null;
        foreach ($items as $i => $item) {
            if (($item['text'] ?? null) === $text && ($item['href'] ?? null) === '#') {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            $items[] = [
                'text' => $text,
                'href' => '#',
                'icon' => $icon,
                'target' => '_self',
                'rule' => $rule,
                'title' => '',
                'children' => [],
            ];
            $index = array_key_last($items);
        }

        $existing = $items[$index]['children'] ?? [];
        foreach ($children as $child) {
            $child = self::completeChild($child);
            $replaced = false;
            foreach ($existing as $j => $old) {
                if (($old['href'] ?? null) === $child['href']) {
                    $existing[$j] = $child;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                $existing[] = $child;
            }
        }
        $items[$index]['children'] = array_values($existing);

        self::writeMenu($items);
    }

    /** Корневой пункт без детей, идемпотентно по href (совпавший заменяется). */
    public static function upsertMenuRoot(string $text, string $href, string $icon, string $rule = self::DEFAULT_RULE): void
    {
        $items = self::readMenu();
        $item = [
            'text' => $text,
            'href' => $href,
            'icon' => $icon,
            'target' => '_self',
            'rule' => $rule,
            'title' => '',
        ];
        foreach ($items as $i => $old) {
            if (($old['href'] ?? null) === $href) {
                $items[$i] = $item;
                self::writeMenu($items);
                return;
            }
        }
        $items[] = $item;
        self::writeMenu($items);
    }

    /**
     * Добавляет пункт в начало группы. Пункт с таким href в группе уже есть — ничего не меняет.
     *
     * @param array<string, mixed> $child
     * @throws \RuntimeException группы нет
     */
    public static function prependMenuChild(string $groupText, array $child): void
    {
        $child = self::completeChild($child);
        $items = self::readMenu();
        foreach ($items as $i => $item) {
            if (($item['text'] ?? null) !== $groupText || ($item['href'] ?? null) !== '#') {
                continue;
            }
            foreach ($item['children'] ?? [] as $old) {
                if (($old['href'] ?? null) === $child['href']) {
                    return;
                }
            }
            $items[$i]['children'] = array_merge([$child], $item['children'] ?? []);
            self::writeMenu($items);
            return;
        }
        throw new \RuntimeException('AdminRegistry: группа меню «' . $groupText . '» не найдена.');
    }

    /** Меняет подпись пункта (корневого или вложенного) по href. */
    public static function renameMenuItem(string $href, string $text): void
    {
        $items = self::readMenu();
        foreach ($items as $i => $item) {
            if (($item['href'] ?? null) === $href) {
                $items[$i]['text'] = $text;
            }
            foreach ($item['children'] ?? [] as $j => $child) {
                if (($child['href'] ?? null) === $href) {
                    $items[$i]['children'][$j]['text'] = $text;
                }
            }
        }
        self::writeMenu($items);
    }

    /**
     * Удаляет пункты по href (корневые и вложенные), затем группы (href '#'), оставшиеся без детей.
     *
     * @param string[] $hrefs
     */
    public static function removeMenuItems(array $hrefs): void
    {
        $result = [];
        foreach (self::readMenu() as $item) {
            if (in_array($item['href'] ?? null, $hrefs, true)) {
                continue;
            }
            if (array_key_exists('children', $item)) {
                $children = array_values(array_filter(
                    $item['children'],
                    static fn (array $c): bool => !in_array($c['href'] ?? null, $hrefs, true)
                ));
                if ($children === [] && ($item['href'] ?? null) === '#') {
                    continue;
                }
                $item['children'] = $children;
            }
            $result[] = $item;
        }
        self::writeMenu($result);
    }

    /**
     * Шаблон виджета дашборда (user_id = NULL), идемпотентно по короткому имени класса.
     * AR AdminWidget в консоли не годится — он требует user_id.
     */
    public static function registerDashboardWidget(string $shortName, string $class, int $ordering): void
    {
        $db = Yii::$app->db;
        $values = [
            'class' => '\\' . ltrim($class, '\\'),
            'ordering' => $ordering,
            'published' => 1,
        ];
        $where = ['alias' => $shortName, 'user_id' => null];
        if ((new Query())->from('{{%admin_widget}}')->where($where)->exists($db)) {
            $db->createCommand()->update('{{%admin_widget}}', $values, $where)->execute();
            return;
        }
        $db->createCommand()->insert('{{%admin_widget}}', $values + $where)->execute();
    }

    /** Удаляет шаблон и пользовательские копии виджета. */
    public static function unregisterDashboardWidget(string $shortName): void
    {
        Yii::$app->db->createCommand()->delete('{{%admin_widget}}', ['alias' => $shortName])->execute();
    }

    /**
     * Выдаёт роли разрешения компонента ({FQCN}\view|create|update|delete), создавая недостающие.
     *
     * @param string[] $actions view, create, update, delete
     * @throws \RuntimeException роли нет или неизвестное действие
     */
    public static function grantModelPermissions(string $modelClass, string $roleName, array $actions): void
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);
        if ($role === null) {
            throw new \RuntimeException('AdminRegistry: роли «' . $roleName . '» нет.');
        }
        foreach ($actions as $action) {
            if (!isset(self::PERMISSION_TITLES[$action])) {
                throw new \RuntimeException('AdminRegistry: неизвестное действие «' . $action . '».');
            }
            $name = $modelClass . '\\' . $action;
            $permission = $auth->getPermission($name);
            if ($permission === null) {
                $permission = $auth->createPermission($name);
                $permission->description = self::PERMISSION_TITLES[$action] . ': ' . StringHelper::basename($modelClass);
                $auth->add($permission);
            }
            if (!$auth->hasChild($role, $permission)) {
                $auth->addChild($role, $permission);
            }
        }
    }

    /**
     * @param array<string, mixed> $child
     * @return array<string, mixed>
     */
    private static function completeChild(array $child): array
    {
        if (empty($child['text']) || empty($child['href'])) {
            throw new \RuntimeException('AdminRegistry: у пункта меню нужны text и href.');
        }
        return $child + [
            'icon' => 'empty',
            'target' => '_self',
            'rule' => self::DEFAULT_RULE,
            'title' => '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function readMenu(): array
    {
        $menu = Menu::findOne(['alias' => self::MENU_ALIAS]);
        if ($menu === null) {
            throw new \RuntimeException('AdminRegistry: в таблице menu нет строки alias=' . self::MENU_ALIAS . '.');
        }
        return json_decode((string)$menu->data, true) ?: [];
    }

    /**
     * Пишет меню через updateAll: без Menu::afterSave, который пересинхронизирует admin_model.in_menu.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private static function writeMenu(array $items): void
    {
        Menu::updateAll(
            ['data' => json_encode(array_values($items), JSON_UNESCAPED_UNICODE)],
            ['alias' => self::MENU_ALIAS]
        );
    }

    private static function saveOrFail(ActiveRecord $record): void
    {
        if (!$record->save()) {
            throw new \RuntimeException(
                'AdminRegistry: ' . $record::class . ': ' . json_encode($record->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }
    }
}
