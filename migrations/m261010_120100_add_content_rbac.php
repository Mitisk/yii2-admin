<?php

use yii\db\Migration;
use yii\rbac\ManagerInterface;

/**
 * Права раздела «Контент» и роль «Контент-менеджер».
 */
class m261010_120100_add_content_rbac extends Migration
{
    private const PERMISSIONS = [
        'viewContent' => 'Контент: просмотр раздела',
        'editContent' => 'Контент: правка значений блоков',
        'manageContent' => 'Контент: управление блоками',
    ];

    public function safeUp(): void
    {
        $auth = $this->auth();

        foreach (self::PERMISSIONS as $name => $description) {
            if ($auth->getPermission($name) === null) {
                $permission = $auth->createPermission($name);
                $permission->description = $description;
                $auth->add($permission);
            }
        }

        $role = $auth->getRole('contentManager');
        if ($role === null) {
            $role = $auth->createRole('contentManager');
            $role->description = 'Контент-менеджер';
            $auth->add($role);
        }

        $admin = $auth->getRole('admin');
        $accessAdmin = $auth->getPermission('accessAdmin');
        if ($accessAdmin !== null && !$auth->hasChild($role, $accessAdmin)) {
            $auth->addChild($role, $accessAdmin);
        }
        foreach (array_keys(self::PERMISSIONS) as $name) {
            $permission = $auth->getPermission($name);
            if (!$auth->hasChild($role, $permission)) {
                $auth->addChild($role, $permission);
            }
            if ($admin !== null && !$auth->hasChild($admin, $permission)) {
                $auth->addChild($admin, $permission);
            }
        }
    }

    public function safeDown(): void
    {
        $auth = $this->auth();
        if ($role = $auth->getRole('contentManager')) {
            $auth->remove($role);
        }
        foreach (array_keys(self::PERMISSIONS) as $name) {
            if ($permission = $auth->getPermission($name)) {
                $auth->remove($permission);
            }
        }
    }

    private function auth(): ManagerInterface
    {
        $auth = Yii::$app->authManager;
        if (!$auth instanceof ManagerInterface) {
            throw new \RuntimeException('Не настроен authManager');
        }
        return $auth;
    }
}
