<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\components;

use Mitisk\Yii2Admin\components\AdminRegistry;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestApp;
use PHPUnit\Framework\TestCase;
use Yii;

/**
 * Реестр в миграциях с минимальным конфигом консоли: только db и authManager.
 */
final class AdminRegistryConsoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestApp::consoleMinimal();
        Yii::$app->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        Yii::$app->db->getTransaction()?->rollBack();
        TestApp::destroy();
        parent::tearDown();
    }

    public function testWorksWithOnlyDbAndAuthManager(): void
    {
        self::assertFalse(Yii::$app->has('settings'));
        self::assertFalse(Yii::$app->has('cache'));

        AdminRegistry::registerComponent([
            'table_name' => 'test_note',
            'model_class' => Note::class,
            'name' => 'Заметки',
            'alias' => 'test-notes',
            'list' => ['title' => ['on' => '1']],
            'data' => [['name' => 'title', 'type' => 'text']],
        ]);
        AdminRegistry::upsertMenuGroup('Каталог', 'fas fa-box', [['text' => 'Заметки', 'href' => '/admin/test-notes/']]);
        AdminRegistry::grantModelPermissions(Note::class, 'manager', ['view']);

        self::assertNotNull(AdminModel::findOne(['table_name' => 'test_note']));
    }
}
