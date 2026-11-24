<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\pages;

use Mitisk\Yii2Admin\models\Page;
use Mitisk\Yii2Admin\tests\TestCase;

/**
 * Автор страницы в вебе — вошедший администратор; модель сериализуема (поведения без замыканий).
 */
final class PageAuthorTest extends TestCase
{
    public function testLoggedInAdminBecomesAuthor(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $page = new Page();
        $page->setAttributes(['slug' => 'authored', 'title' => 'С автором', 'status' => 'published'], false);

        self::assertTrue($page->save(), print_r($page->getErrors(), true));
        self::assertSame(self::ADMIN_ID, (int) $page->created_by);
        self::assertSame(self::ADMIN_ID, (int) $page->updated_by);
    }

    public function testPageWithBehaviorsCanBeSerialized(): void
    {
        $page = new Page(['slug' => 'cached', 'title' => 'В кэше']);
        $page->getBehaviors();

        $copy = unserialize(serialize($page));

        self::assertSame('В кэше', $copy->title);
    }
}
