<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\testing;

use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\Module;
use Mitisk\Yii2Admin\testing\AdminSession;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Вход в админку из тестов проекта.
 */
final class AdminSessionTest extends TestCase
{
    public function testLogsInAsAdminByDefault(): void
    {
        $user = AdminSession::login();

        self::assertFalse(Yii::$app->adminUser->isGuest);
        self::assertSame($user->id, (int) Yii::$app->adminUser->id);
        self::assertTrue(Yii::$app->authManager->checkAccess($user->id, 'admin'));
    }

    public function testCreatesMissingRoleAndPassesExtraColumns(): void
    {
        $user = AdminSession::login(['reportViewer'], ['name' => 'Тестовый аналитик'], 'analyst');

        self::assertSame('Тестовый аналитик', $user->name);
        self::assertTrue(Yii::$app->authManager->checkAccess($user->id, 'reportViewer'));
        self::assertFalse(Yii::$app->authManager->checkAccess($user->id, 'admin'));
    }

    public function testRepeatedLoginReusesUser(): void
    {
        $first = AdminSession::login();
        $second = AdminSession::login();

        self::assertSame($first->id, $second->id);
        self::assertSame(1, (int) AdminUser::find()->where(['username' => 'test-admin'])->count());
    }

    public function testModuleDoesNotRedirectToUpgradePage(): void
    {
        Yii::$app->settings->set('GENERAL', 'version', '0.0.1');

        AdminSession::login();

        self::assertSame(Module::VERSION, Yii::$app->settings->get('GENERAL', 'version'));
        self::assertFalse(Yii::$app->getModule('admin')->needsUpgrade());
    }
}
