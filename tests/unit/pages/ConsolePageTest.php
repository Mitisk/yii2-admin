<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\pages;

use Mitisk\Yii2Admin\models\Page;
use Mitisk\Yii2Admin\tests\TestApp;
use PHPUnit\Framework\TestCase;
use Yii;

/**
 * Страницы создаются и из консоли (миграции, команды проекта), где модуль admin тоже подключён.
 */
final class ConsolePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestApp::console(withModule: true);
        Yii::$app->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        Yii::$app->db->getTransaction()?->rollBack();
        TestApp::destroy();
        parent::tearDown();
    }

    public function testPageIsSavedFromConsoleWithoutAuthor(): void
    {
        $page = new Page();
        $page->setAttributes(['slug' => 'from-console', 'title' => 'Из миграции', 'status' => 'published'], false);

        self::assertTrue($page->save(), print_r($page->getErrors(), true));
        self::assertNull($page->created_by);
    }
}
