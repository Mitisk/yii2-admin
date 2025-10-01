<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminControllerMap;
use Mitisk\Yii2Admin\models\AdminModel;
use Yii;
use yii\base\Model;
use yii\db\ActiveRecordInterface;

/**
 * Реестр зарезервированных алиасов для роутинга/моделей админ‑панели.
 *
 * Источники:
 *  - Технические алиасы (встроенные контроллеры/действия).
 *  - Алиасы контроллеров из AdminControllerMap::controller_id.
 *  - Алиасы моделей из AdminModel::alias.
 *
 * Поддерживает нормализацию, дедупликацию и кэширование результатов.
 */
final class ReservedAlias
{
    /**
     * Базовые технические алиасы, запрещенные к использованию.
     *
     * Примечание: 'index' — действие по умолчанию контроллера; 'default' — маршрут/контроллер по умолчанию в модуле.
     *
     * @var string[]
     */
    private const TECHNICAL = [
        'index',
        'error',
        'captcha',
        'contact',
        'login',
        'logout',
        'default',
        'auth',
        'user',
        'settings',
        'role',
        'menu',
        'components',
        'ajax',
    ];

    /**
     * Вернуть список технических алиасов как есть.
     *
     * @return string[]
     */
    public static function get() : array
    {
        return self::TECHNICAL;
    }

    /**
     * Вернуть полный список зарезервированных алиасов.
     *
     * @param bool $unique     Удалять дубликаты.
     * @param bool $normalize  Нормализовать (trim + strtolower) и отфильтровать пустые.
     * @param int|null $cacheTtl Кэшировать в Yii::$app->cache, секунды; null — без кэша.
     *
     * @return string[] Отсортированный список алиасов.
     */
    public static function getAll(bool $unique = true, bool $normalize = true, ?int $cacheTtl = 300) : array
    {
        $key = [__CLASS__, __METHOD__, $unique, $normalize];

        $resolver = static function () use ($unique, $normalize) : array {
            $technical = self::TECHNICAL;

            $fromControllerMap = AdminControllerMap::find()
                ->select('controller_id')
                ->column();

            $fromAdminModels = AdminModel::find()
                ->select('alias')
                ->column();

            $all = array_merge($technical, $fromControllerMap, $fromAdminModels);

            if ($normalize) {
                $all = array_map(
                    static fn($v) => is_string($v) ? strtolower(trim($v)) : '',
                    $all
                );
                $all = array_filter($all, static fn($v) => $v !== '');
            }

            if ($unique) {
                $all = array_values(array_unique($all, SORT_STRING));
            }

            sort($all, SORT_STRING);
            return $all;
        };

        if ($cacheTtl !== null && Yii::$app->has('cache')) {
            return Yii::$app->cache->getOrSet($key, $resolver, $cacheTtl);
        }

        return $resolver();
    }

    /**
     * Валидация alias для модели AdminModel: запрещает технические и контроллерные алиасы,
     * исключая текущую запись модели по PK при обновлении.
     *
     * Подключать в rules() через замыкание: ['alias', fn($attr) => ReservedAlias::validateForModel($this, $attr)].
     *
     * @param Model $model Экземпляр модели (желательно ActiveRecord для определения PK).
     * @param string $attribute Имя проверяемого атрибута (например, 'alias').
     * @param int|null $cacheTtl TTL кэша для справочников (сек), null — без кэша.
     */
    public static function validateForModel(Model $model, string $attribute, ?int $cacheTtl = 300): void
    {
        $raw = (string)$model->$attribute;
        $value = strtolower(trim($raw));
        if ($value === '') {
            return;
        }

        $excludeId = null;
        if ($model instanceof ActiveRecordInterface) {
            $pk = $model->getPrimaryKey();
            if ($pk !== null) {
                $excludeId = (int)$pk;
            }
        }

        $key = [__METHOD__, $excludeId];

        $resolver = static function () use ($excludeId): array {
            $controllers = AdminControllerMap::find()
                ->select('controller_id')
                ->column();

            $query = AdminModel::find()->select('alias');
            if ($excludeId !== null) {
                $query->andWhere(['<>', 'id', $excludeId]);
            }
            $aliases = $query->column();

            $list = array_merge(self::TECHNICAL, $controllers, $aliases);
            $list = array_map(
                static fn($v) => is_string($v) ? strtolower(trim($v)) : '',
                $list
            );
            $list = array_values(array_filter($list, static fn($v) => $v !== ''));
            $list = array_values(array_unique($list, SORT_STRING));
            return $list;
        };

        $reserved = ($cacheTtl !== null && Yii::$app->has('cache'))
            ? Yii::$app->cache->getOrSet($key, $resolver, $cacheTtl)
            : $resolver();

        if (in_array($value, $reserved, true)) {
            $model->addError($attribute, 'Этот алиас зарезервирован системой.');
        }
    }

    /**
     * Валидация controller_id для модели AdminControllerMap: запрещает технические и модельные алиасы,
     * исключая текущую запись карты контроллеров по PK при обновлении.
     *
     * Подключать в rules() через замыкание: ['controller_id', fn($attr) => ReservedAlias::validateForControllerMap($this, $attr)].
     *
     * @param Model $model Экземпляр модели (желательно ActiveRecord для определения PK).
     * @param string $attribute Имя проверяемого атрибута (например, 'controller_id').
     * @param int|null $cacheTtl TTL кэша для справочников (сек), null — без кэша.
     */
    public static function validateForControllerMap(Model $model, string $attribute, ?int $cacheTtl = 300): void
    {
        $raw = (string)$model->$attribute;
        $value = strtolower(trim($raw));
        if ($value === '') {
            return;
        }

        $excludeId = null;
        if ($model instanceof ActiveRecordInterface) {
            $pk = $model->getPrimaryKey();
            if ($pk !== null) {
                $excludeId = (int)$pk;
            }
        }

        $key = [__METHOD__, $excludeId];

        $resolver = static function () use ($excludeId): array {
            $query = AdminControllerMap::find()->select('controller_id');
            if ($excludeId !== null) {
                $query->andWhere(['<>', 'id', $excludeId]);
            }
            $controllers = $query->column();

            $aliases = AdminModel::find()
                ->select('alias')
                ->column();

            $list = array_merge(self::TECHNICAL, $controllers, $aliases);
            $list = array_map(
                static fn($v) => is_string($v) ? strtolower(trim($v)) : '',
                $list
            );
            $list = array_values(array_filter($list, static fn($v) => $v !== ''));
            $list = array_values(array_unique($list, SORT_STRING));
            return $list;
        };

        $reserved = ($cacheTtl !== null && Yii::$app->has('cache'))
            ? Yii::$app->cache->getOrSet($key, $resolver, $cacheTtl)
            : $resolver();

        if (in_array($value, $reserved, true)) {
            $model->addError($attribute, 'Этот идентификатор контроллера зарезервирован системой.');
        }
    }
}
