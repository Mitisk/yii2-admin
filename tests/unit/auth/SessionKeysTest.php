<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\auth;

use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\web\User;

/**
 * Вход на сайте и в админке в одном браузере: у adminUser все ключи сессии свои.
 *
 * Раньше свой был только idParam, а __authKey — общий с пользователем сайта: вход в одном месте
 * перетирал ключ другого, тот при следующем запросе вылетал («Invalid session auth key attempted»),
 * входил заново по cookie и перетирал ключ обратно.
 */
final class SessionKeysTest extends TestCase
{
    private const SESSION_PARAMS = [
        'idParam',
        'authKeyParam',
        'authTimeoutParam',
        'absoluteAuthTimeoutParam',
        'returnUrlParam',
    ];

    public function testSiteLoginKeepsAdminLoggedIn(): void
    {
        $admin = $this->admin();
        $visitor = $this->createUser('site-visitor');

        Yii::$app->adminUser->login($admin);
        $this->siteUser()->login($visitor);

        self::assertSame($admin->id, (int) $this->freshAdminUser()->getId());
        self::assertSame($visitor->id, (int) $this->siteUser()->getId());
    }

    public function testAdminLoginKeepsSiteVisitorLoggedIn(): void
    {
        $admin = $this->admin();
        $visitor = $this->createUser('site-visitor');

        $this->siteUser()->login($visitor);
        Yii::$app->adminUser->login($admin);

        self::assertSame($visitor->id, (int) $this->siteUser()->getId());
        self::assertSame($admin->id, (int) $this->freshAdminUser()->getId());
    }

    public function testNoSessionKeyIsSharedWithSiteUser(): void
    {
        $site = new User(['identityClass' => AdminUser::class]);
        $admin = Yii::$app->adminUser;

        foreach (self::SESSION_PARAMS as $param) {
            self::assertNotSame($site->$param, $admin->$param, $param);
        }
        self::assertNotSame($site->identityCookie['name'], $admin->identityCookie['name']);
    }

    private function admin(): AdminUser
    {
        $admin = AdminUser::findOne(self::ADMIN_ID);
        self::assertNotNull($admin);

        return $admin;
    }

    /**
     * Пользователь сайта со значениями yii\web\User по умолчанию — как `user` в конфиге проекта.
     * Каждый вызов — новый объект: личность заново читается из сессии, как в следующем запросе.
     */
    private function siteUser(): User
    {
        return new User(['identityClass' => AdminUser::class, 'enableAutoLogin' => false]);
    }

    /** adminUser по конфигу модуля, как в следующем запросе. */
    private function freshAdminUser(): User
    {
        /** @var User $user */
        $user = Yii::createObject(Yii::$app->getComponents(true)['adminUser']);

        return $user;
    }
}
