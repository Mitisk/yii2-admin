<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\menu;

use Mitisk\Yii2Admin\models\Menu;
use Mitisk\Yii2Admin\tests\TestCase;
use yii\web\MethodNotAllowedHttpException;

/**
 * Редактор меню /admin/menu/: сохранение всех меню одним запросом, чистка пунктов, удаление.
 */
final class MenuEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs(self::ADMIN_ID);
    }

    public function testItemsAreCleanedOnSave(): void
    {
        $admin = $this->adminMenu();

        $result = $this->save([[
            'id' => $admin->id,
            'items' => [
                ['text' => ' Главная ', 'href' => '/admin/', 'icon' => 'fas fa-home', 'rule' => 'accessAdmin', 'onclick' => 'alert(1)'],
                ['text' => 'Ловушка', 'href' => 'javascript:alert(1)', 'icon' => '"><script>', 'target' => 'popup'],
                ['text' => '', 'href' => '/admin/empty/'],
                ['text' => 'Раздел', 'href' => '#', 'children' => [
                    ['text' => 'Вложенный', 'href' => '/admin/a/', 'children' => [
                        ['text' => 'Третий уровень', 'href' => '/admin/b/'],
                    ]],
                ]],
            ],
        ]]);

        self::assertTrue($result['success'], json_encode($result, JSON_UNESCAPED_UNICODE));
        $items = json_decode((string) $this->adminMenu()->data, true);
        self::assertCount(3, $items, 'Пункт без названия не сохраняется');
        self::assertSame('Главная', $items[0]['text']);
        self::assertArrayNotHasKey('onclick', $items[0]);
        self::assertSame('#', $items[1]['href'], 'javascript: в ссылке');
        self::assertSame('', $items[1]['icon']);
        self::assertSame('_self', $items[1]['target']);
        self::assertSame('Вложенный', $items[2]['children'][0]['text']);
        self::assertArrayNotHasKey('children', $items[2]['children'][0], 'Боковое меню показывает два уровня');
    }

    public function testItemWithoutRuleStaysVisibleToEveryone(): void
    {
        $admin = $this->adminMenu();

        $this->save([['id' => $admin->id, 'items' => [['text' => 'Справка', 'href' => '/admin/help/', 'rule' => '']]]]);

        $items = json_decode((string) $this->adminMenu()->data, true);
        self::assertSame('', $items[0]['rule']);
    }

    public function testSystemMenuKeepsNameAndAlias(): void
    {
        $admin = $this->adminMenu();
        $name = $admin->name;

        $this->save([['id' => $admin->id, 'name' => 'Взлом', 'alias' => 'hacked', 'items' => [['text' => 'Главная', 'href' => '/admin/']]]]);

        $admin = Menu::findOne($admin->id);
        self::assertSame('admin', $admin->alias);
        self::assertSame($name, $admin->name);
    }

    public function testSeveralMenusAreSavedByOneRequest(): void
    {
        $admin = $this->adminMenu();

        $result = $this->save([
            ['id' => $admin->id, 'items' => [['text' => 'Главная', 'href' => '/admin/']]],
            ['id' => null, 'name' => 'Подвал сайта', 'alias' => 'footer', 'items' => [['text' => 'Контакты', 'href' => '/contacts/']]],
        ]);

        self::assertTrue($result['success'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertCount(1, json_decode((string) $this->adminMenu()->data, true));
        $footer = Menu::findOne(['alias' => 'footer']);
        self::assertNotNull($footer);
        self::assertSame('Подвал сайта', $footer->name);
        self::assertSame('Контакты', json_decode((string) $footer->data, true)[0]['text']);
    }

    public function testInvalidMenuStopsWholeSave(): void
    {
        $admin = $this->adminMenu();
        $before = $admin->data;

        $result = $this->save([
            ['id' => $admin->id, 'items' => [['text' => 'Изменено', 'href' => '/admin/']]],
            ['id' => null, 'name' => 'Дубль', 'alias' => 'admin', 'items' => []],
        ]);

        self::assertFalse($result['success']);
        self::assertNotEmpty($result['errors'][1] ?? null, 'Ошибка привязана к меню, в котором она случилась');
        self::assertSame($before, $this->adminMenu()->data, 'Часть меню сохранилась, часть нет');
        self::assertSame(1, (int) Menu::find()->where(['alias' => 'admin'])->count());
    }

    public function testNewMenuNeedsName(): void
    {
        $result = $this->save([['id' => null, 'name' => '', 'alias' => 'footer', 'items' => []]]);

        self::assertFalse($result['success']);
        self::assertNull(Menu::findOne(['alias' => 'footer']));
    }

    public function testMenuIsNotDeletedByLink(): void
    {
        $menu = $this->footerMenu();

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->runAction('admin/menu/delete', ['id' => $menu->id]);
    }

    public function testMenuIsDeletedByPostButSystemMenuStays(): void
    {
        $menu = $this->footerMenu();

        $this->runAction('admin/menu/delete', ['id' => $menu->id], []);
        $this->runAction('admin/menu/delete', ['id' => $this->adminMenu()->id], []);

        self::assertNull(Menu::findOne($menu->id));
        self::assertNotNull(Menu::findOne(['alias' => 'admin']));
    }

    public function testPageEscapesMenuNamesAndData(): void
    {
        $menu = $this->footerMenu();
        $menu->updateAttributes([
            'name' => '</script><b>Подвал</b>',
            'data' => json_encode([['text' => '</script><i>x</i>', 'href' => '/']]),
        ]);

        $html = $this->runAction('admin/menu/index');

        self::assertIsString($html);
        self::assertStringNotContainsString('</script><b>Подвал', $html);
        self::assertStringNotContainsString('</script><i>x', $html);
        self::assertStringContainsString('me-editor', $html);
    }

    private function adminMenu(): Menu
    {
        $menu = Menu::findOne(['alias' => 'admin']);
        self::assertNotNull($menu, 'В тестовой базе нет меню admin');

        return $menu;
    }

    private function footerMenu(): Menu
    {
        $menu = new Menu(['alias' => 'footer', 'name' => 'Подвал', 'data' => '[]', 'ordering' => 5]);
        self::assertTrue($menu->save(), print_r($menu->getErrors(), true));

        return $menu;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     * @return array<string, mixed>
     */
    private function save(array $menus): array
    {
        $result = $this->runAction('admin/menu/save', [], ['menus' => json_encode($menus, JSON_UNESCAPED_UNICODE)]);
        self::assertIsArray($result);

        return $result;
    }
}
