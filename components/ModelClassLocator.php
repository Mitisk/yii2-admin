<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Connection;
use yii\helpers\FileHelper;

/**
 * Поиск ActiveRecord-классов приложения, привязанных к заданной таблице БД.
 *
 * Сканирует настроенные директории с моделями и возвращает FQCN классов,
 * чей tableName() указывает на искомую таблицу. Используется для
 * автоподстановки класса модели при создании компонента админки.
 */
final class ModelClassLocator
{
    /**
     * @param array<string, string> $namespaces Карта «неймспейс => путь/алиас
     *     директории», например ['app\models' => '@app/models'].
     */
    public function __construct(
        private readonly array $namespaces = ['app\models' => '@app/models'],
    ) {
    }

    /**
     * Возвращает отсортированный список FQCN ActiveRecord-классов,
     * чья таблица совпадает с $tableName (с учётом префикса таблиц).
     *
     * @param string $tableName Имя таблицы («сырое» или в формате {{%name}})
     * @param Connection|null $db Соединение для разрешения префикса
     *     (по умолчанию Yii::$app->db)
     * @return array<int, class-string<ActiveRecord>>
     */
    public function findByTable(string $tableName, ?Connection $db = null): array
    {
        $db ??= Yii::$app->getDb();
        $schema = $db->getSchema();
        $target = $schema->getRawTableName($tableName);

        $found = [];

        foreach ($this->namespaces as $namespace => $path) {
            $dir = Yii::getAlias($path, false);
            if ($dir === false || !is_dir($dir)) {
                continue;
            }
            $dir = (string) FileHelper::normalizePath($dir);

            foreach (FileHelper::findFiles($dir, ['only' => ['*.php']]) as $file) {
                $class = self::classNameFromFile($namespace, $dir, $file);

                try {
                    if (!class_exists($class)
                        || !is_subclass_of($class, ActiveRecord::class)
                        || (new \ReflectionClass($class))->isAbstract()
                    ) {
                        continue;
                    }
                    if ($schema->getRawTableName($class::tableName()) === $target) {
                        $found[] = $class;
                    }
                } catch (\Throwable) {
                    // Битый класс (ошибка автозагрузки, фатальный tableName()
                    // и т.п.) не должен ломать построение формы — пропускаем.
                    continue;
                }
            }
        }

        sort($found);

        return array_values(array_unique($found));
    }

    /**
     * Выводит FQCN класса из пути файла относительно корня неймспейса.
     *
     * @param string $namespace Корневой неймспейс директории
     * @param string $dir Абсолютный нормализованный путь директории
     * @param string $file Абсолютный путь PHP-файла внутри $dir
     * @return string
     */
    private static function classNameFromFile(string $namespace, string $dir, string $file): string
    {
        $relative = substr((string) FileHelper::normalizePath($file), strlen($dir));
        $relative = trim(str_replace(DIRECTORY_SEPARATOR, '\\', $relative), '\\');

        return $namespace . '\\' . substr($relative, 0, -strlen('.php'));
    }
}
