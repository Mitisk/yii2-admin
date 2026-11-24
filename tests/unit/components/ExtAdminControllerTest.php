<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\components;

use Mitisk\Yii2Admin\tests\_app\controllers\ReportsController;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Свои экраны админки (admin_controller_map): по умолчанию — только роль admin.
 */
final class ExtAdminControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mount([]);
    }

    public function testAdminOpensScreenByDefault(): void
    {
        $this->loginAs(self::ADMIN_ID);

        self::assertSame('reports', $this->runAction('admin/test-reports/index'));
    }

    public function testUserWithoutAdminRoleIsForbiddenByDefault(): void
    {
        $this->loginAs($this->createUser('editor')->id);

        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('admin/test-reports/index');
    }

    public function testAccessCanBeOpenedToAnyAdminPanelUser(): void
    {
        $this->mount(['access' => '@']);
        $this->loginAs($this->createUser('editor')->id);

        self::assertSame('reports', $this->runAction('admin/test-reports/index'));
    }

    public function testAccessByPermissionFromControllerMapConfig(): void
    {
        $this->mount(['access' => ['viewReports']]);
        $this->loginAs($this->createUser('analyst', ['viewReports'])->id);

        self::assertSame('reports', $this->runAction('admin/test-reports/index'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeReturnProvider')]
    public function testRedirectBackIgnoresForeignReturnAddress(string $return): void
    {
        $this->loginAs(self::ADMIN_ID);

        $response = $this->runAction('admin/test-reports/back', ['return' => $return]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('/admin/reports/', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    public function testRedirectBackFollowsLocalReturnAddress(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $response = $this->runAction('admin/test-reports/back', ['return' => '/admin/page/?status=draft']);

        self::assertSame('http://localhost/admin/page/?status=draft', $response->headers->get('Location'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeReturnProvider(): array
    {
        return [
            'другой сайт' => ['https://evil.example/'],
            'без схемы' => ['//evil.example/'],
            'обратный слеш' => ['/\\evil.example/'],
            'перевод строки' => ["/admin/\r\nSet-Cookie: x=1"],
            'относительный' => ['admin/page/'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mount(array $config): void
    {
        Yii::$app->getModule('admin')->controllerMap['test-reports'] = ['class' => ReportsController::class] + $config;
    }
}
