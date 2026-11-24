<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\testing;

use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\Module;
use Yii;
use yii\rbac\DbManager;

/**
 * Вход в админку из тестов проекта (Codeception functional, PHPUnit с веб-приложением).
 *
 * Создаёт пользователя админки (или берёт по username), даёт роли (недостающие создаёт),
 * записывает GENERAL.version — без неё любая страница /admin/* уводит на /admin/default/upgrade —
 * и логинит adminUser. Всё пишется в текущую транзакцию теста.
 *
 * ```php
 * AdminSession::login();                                  // роль admin
 * AdminSession::login(['manager'], ['role' => 9]);        // своя роль; обязательные колонки user проекта
 * $I->amOnPage('/admin/products/');
 * ```
 */
final class AdminSession
{
    /**
     * @param string[] $roles роли пользователя; [] — без ролей (проверка отказа в доступе)
     * @param array<string, mixed> $attributes дополнительные колонки строки user (обязательные колонки проекта)
     */
    public static function login(array $roles = ['admin'], array $attributes = [], string $username = 'test-admin'): AdminUser
    {
        // Модуль регистрирует adminUser, authManager и компоненты админки
        Yii::$app->getModule('admin');

        $user = self::ensureUser($username, $attributes);
        self::assignRoles($user->id, $roles);
        Yii::$app->settings->set('GENERAL', 'version', Module::VERSION);

        Yii::$app->get('adminUser')->login($user);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function ensureUser(string $username, array $attributes): AdminUser
    {
        $db = Yii::$app->db;
        $now = time();
        $row = $attributes + [
            'email' => $username . '@example.test',
            'password_hash' => Yii::$app->security->generatePasswordHash('test', 4),
            'auth_key' => Yii::$app->security->generateRandomString(32),
            'status' => AdminUser::STATUS_ACTIVE,
            'updated_at' => $now,
        ];

        $id = AdminUser::find()->select('id')->where(['username' => $username])->scalar();
        if ($id !== false && $id !== null) {
            $db->createCommand()->update(AdminUser::tableName(), $row, ['id' => (int)$id])->execute();
        } else {
            $db->createCommand()->insert(AdminUser::tableName(), $row + ['username' => $username, 'created_at' => $now])->execute();
            $id = (int)$db->getLastInsertID();
        }

        $user = AdminUser::findOne((int)$id);
        if ($user === null) {
            throw new \RuntimeException("AdminSession: пользователь {$username} не создан.");
        }
        return $user;
    }

    /**
     * @param string[] $roles
     */
    private static function assignRoles(int $userId, array $roles): void
    {
        $auth = Yii::$app->authManager;
        foreach ($roles as $name) {
            $role = $auth->getRole($name);
            if ($role === null) {
                $role = $auth->createRole($name);
                $auth->add($role);
            }
            if ($auth->getAssignment($name, (string)$userId) === null) {
                $auth->assign($role, (string)$userId);
            }
        }
        if ($auth instanceof DbManager) {
            $auth->invalidateCache();
        }
    }
}
