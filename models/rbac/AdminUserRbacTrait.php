<?php
namespace Mitisk\Yii2Admin\models\rbac;

use yii\helpers\ArrayHelper;
use Yii;

/**
 * Расширение модели AdminUser для работы с RBAC
 *
 * Добавьте эти методы в вашу существующую модель AdminUser
 * или создайте trait для повторного использования
 */
trait AdminUserRbacTrait
{
    /**
     * Получить роли пользователя
     *
     * @return array
     */
    public function getUserRoles()
    {
        if (!Yii::$app->authManager) {
            return [];
        }

        $roles = Yii::$app->authManager->getRolesByUser($this->id);
        return ArrayHelper::map($roles, 'name', 'name');
    }

    /**
     * Получить разрешения пользователя
     *
     * @return array
     */
    public function getUserPermissions()
    {
        if (!Yii::$app->authManager) {
            return [];
        }

        $permissions = Yii::$app->authManager->getPermissionsByUser($this->id);
        return ArrayHelper::map($permissions, 'name', 'description');
    }

    /**
     * Проверить, есть ли у пользователя роль
     *
     * @param string $roleName
     * @return bool
     */
    public function hasRole($roleName)
    {
        $roles = $this->getUserRoles();
        return in_array($roleName, $roles);
    }

    /**
     * Проверить разрешение пользователя
     *
     * @param string $permissionName
     * @param array $params
     * @return bool
     */
    public function can($permissionName, $params = [])
    {
        if (!Yii::$app->authManager) {
            return false;
        }

        return Yii::$app->authManager->checkAccess($this->id, $permissionName, $params);
    }

    /**
     * Назначить роль пользователю
     *
     * @param string $roleName
     * @return bool
     * @throws \Exception
     */
    public function assignRole($roleName)
    {
        if (!Yii::$app->authManager) {
            throw new \Exception('AuthManager не настроен');
        }

        $role = Yii::$app->authManager->getRole($roleName);
        if (!$role) {
            throw new \Exception("Роль '{$roleName}' не найдена");
        }

        try {
            Yii::$app->authManager->assign($role, $this->id);
            return true;
        } catch (\Exception $e) {
            Yii::error("Ошибка назначения роли {$roleName} пользователю {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отозвать роль у пользователя
     *
     * @param string $roleName
     * @return bool
     * @throws \Exception
     */
    public function revokeRole($roleName)
    {
        if (!Yii::$app->authManager) {
            throw new \Exception('AuthManager не настроен');
        }

        $role = Yii::$app->authManager->getRole($roleName);
        if (!$role) {
            throw new \Exception("Роль '{$roleName}' не найдена");
        }

        try {
            Yii::$app->authManager->revoke($role, $this->id);
            return true;
        } catch (\Exception $e) {
            Yii::error("Ошибка отзыва роли {$roleName} у пользователя {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить список ролей для dropdown
     *
     * @return array
     */
    public static function getRolesList()
    {
        if (!Yii::$app->authManager) {
            return [];
        }

        $roles = Yii::$app->authManager->getRoles();
        $rolesList = [];

        foreach ($roles as $role) {
            // Проверяем, может ли текущий пользователь назначать эту роль
            if (static::canCurrentUserAssignRole($role->name)) {
                $rolesList[$role->name] = $role->description ?: $role->name;
            }
        }

        return $rolesList;
    }

    /**
     * Проверить, может ли текущий пользователь назначать роль
     *
     * @param string $roleName
     * @return bool
     */
    protected static function canCurrentUserAssignRole($roleName)
    {
        // Базовые проверки
        if (Yii::$app->user->isGuest) {
            return false;
        }

        if (!Yii::$app->user->can('manageUserRoles')) {
            return false;
        }

        // Дополнительные ограничения
        if ($roleName === 'superAdminRole' && !Yii::$app->user->can('superAdmin')) {
            return false;
        }

        return true;
    }

    /**
     * Переопределяем afterSave для обновления RBAC кэша
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // Очищаем RBAC кэш при изменении пользователя
        if (Yii::$app->authManager && isset(Yii::$app->authManager->cache)) {
            Yii::$app->authManager->invalidateCache();
        }
    }

    /**
     * Переопределяем afterDelete для очистки RBAC назначений
     */
    public function afterDelete()
    {
        parent::afterDelete();

        // Удаляем все RBAC назначения при удалении пользователя
        if (Yii::$app->authManager) {
            Yii::$app->authManager->revokeAll($this->id);
        }
    }
}