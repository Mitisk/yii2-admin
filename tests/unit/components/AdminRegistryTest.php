<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\components;

use Mitisk\Yii2Admin\components\AdminRegistry;
use Mitisk\Yii2Admin\models\AdminControllerMap;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\Menu;
use Mitisk\Yii2Admin\tests\_app\controllers\ReportsController;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\db\Query;

/**
 * Регистрация сущностей проекта в админке из миграций: идемпотентно, с ошибкой при неудаче.
 */
final class AdminRegistryTest extends TestCase
{
    private const NOTE = [
        'table_name' => 'test_note',
        'model_class' => Note::class,
        'name' => 'Заметки',
        'alias' => 'test-notes',
        'admin_label' => 'title',
        'can_create' => true,
        'list' => ['title' => ['on' => '1']],
        'data' => [['name' => 'title', 'type' => 'text', 'hint' => "Строка 1\nСтрока 2"]],
    ];

    public function testRegistersComponentWithArraysAsJson(): void
    {
        $component = AdminRegistry::registerComponent(self::NOTE);

        $stored = AdminModel::findOne($component->id);
        self::assertSame(Note::class, $stored->model_class);
        self::assertSame(1, (int) $stored->view);
        self::assertSame(1, (int) $stored->can_create);
        self::assertSame(['title' => ['on' => '1']], json_decode((string) $stored->list, true));
        self::assertSame("Строка 1\nСтрока 2", json_decode((string) $stored->data, true)[0]['hint']);
    }

    public function testRepeatedRegistrationUpdatesSameRow(): void
    {
        $first = AdminRegistry::registerComponent(self::NOTE);
        $second = AdminRegistry::registerComponent(['name' => 'Записки'] + self::NOTE);

        self::assertSame($first->id, $second->id);
        self::assertSame(1, (int) AdminModel::find()->where(['table_name' => 'test_note'])->count());
        self::assertSame('Записки', AdminModel::findOne($first->id)->name);
    }

    public function testEmptyListIsStoredAsJson(): void
    {
        $component = AdminRegistry::registerComponent(['list' => []] + self::NOTE);

        self::assertIsArray(json_decode((string) AdminModel::findOne($component->id)->list, true));
    }

    public function testInvalidComponentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        AdminRegistry::registerComponent(['alias' => 'content-block'] + self::NOTE);
    }

    public function testUnregisterComponentRemovesRow(): void
    {
        AdminRegistry::registerComponent(self::NOTE);

        AdminRegistry::unregisterComponent('test_note');

        self::assertNull(AdminModel::findOne(['table_name' => 'test_note']));
    }

    public function testRegistersControllerOnceWithConfig(): void
    {
        AdminRegistry::registerController('test-reports', ReportsController::class, ['access' => '@']);
        AdminRegistry::registerController('test-reports', ReportsController::class, ['access' => 'viewReports']);

        $rows = AdminControllerMap::find()->where(['controller_id' => 'test-reports'])->all();
        self::assertCount(1, $rows);
        $config = is_array($rows[0]->config) ? $rows[0]->config : json_decode((string) $rows[0]->config, true);
        self::assertSame(['access' => 'viewReports'], $config);

        AdminRegistry::unregisterController('test-reports');
        self::assertFalse(AdminControllerMap::find()->where(['controller_id' => 'test-reports'])->exists());
    }

    public function testMenuGroupIsCreatedAndMergedByHref(): void
    {
        AdminRegistry::upsertMenuGroup('Каталог', 'fas fa-box', [
            ['text' => 'Заметки', 'href' => '/admin/test-notes/'],
        ]);
        AdminRegistry::upsertMenuGroup('Каталог', 'fas fa-box', [
            ['text' => 'Все заметки', 'href' => '/admin/test-notes/'],
            ['text' => 'Отчёты', 'href' => '/admin/test-reports/'],
        ]);

        $group = $this->menuItem('Каталог');
        self::assertSame('#', $group['href']);
        self::assertSame(['Все заметки', 'Отчёты'], array_column($group['children'], 'text'));
        self::assertSame('admin', $group['children'][0]['rule']);
    }

    public function testPrependRenameAndRemoveMenuItems(): void
    {
        AdminRegistry::upsertMenuGroup('Каталог', 'fas fa-box', [['text' => 'Заметки', 'href' => '/admin/test-notes/']]);
        AdminRegistry::prependMenuChild('Каталог', ['text' => 'Отчёты', 'href' => '/admin/test-reports/']);
        AdminRegistry::prependMenuChild('Каталог', ['text' => 'Отчёты', 'href' => '/admin/test-reports/']);
        AdminRegistry::renameMenuItem('/admin/test-notes/', 'Записки');

        self::assertSame(['Отчёты', 'Записки'], array_column($this->menuItem('Каталог')['children'], 'text'));

        AdminRegistry::removeMenuItems(['/admin/test-notes/', '/admin/test-reports/']);
        self::assertNull($this->menuItem('Каталог'), 'Пустая группа должна исчезнуть');
    }

    public function testMenuRootIsUpsertedByHref(): void
    {
        AdminRegistry::upsertMenuRoot('Отчёты', '/admin/test-reports/', 'fas fa-chart-bar');
        AdminRegistry::upsertMenuRoot('Аналитика', '/admin/test-reports/', 'fas fa-chart-line', 'viewReports');

        $items = array_filter($this->menu(), static fn (array $i): bool => $i['href'] === '/admin/test-reports/');
        self::assertCount(1, $items);
        $item = array_values($items)[0];
        self::assertSame('Аналитика', $item['text']);
        self::assertSame('viewReports', $item['rule']);
    }

    public function testDashboardWidgetTemplateIsUpserted(): void
    {
        AdminRegistry::registerDashboardWidget('NotesWidget', 'app\\widgets\\NotesWidget', 5);
        AdminRegistry::registerDashboardWidget('NotesWidget', 'app\\widgets\\NotesWidget', 7);

        $rows = (new Query())->from('{{%admin_widget}}')->where(['alias' => 'NotesWidget'])->all();
        self::assertCount(1, $rows);
        self::assertSame('\\app\\widgets\\NotesWidget', $rows[0]['class']);
        self::assertSame(7, (int) $rows[0]['ordering']);
        self::assertNull($rows[0]['user_id']);

        AdminRegistry::unregisterDashboardWidget('NotesWidget');
        self::assertFalse((new Query())->from('{{%admin_widget}}')->where(['alias' => 'NotesWidget'])->exists());
    }

    public function testGrantsModelPermissionsToRole(): void
    {
        AdminRegistry::grantModelPermissions(Note::class, 'moderator', ['view', 'update']);

        $auth = Yii::$app->authManager;
        $granted = array_keys($auth->getPermissionsByRole('moderator'));
        self::assertContains(Note::class . '\\view', $granted);
        self::assertContains(Note::class . '\\update', $granted);
        self::assertNotContains(Note::class . '\\delete', $granted);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function menu(): array
    {
        return json_decode((string) Menu::findOne(['alias' => 'admin'])->data, true) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function menuItem(string $text): ?array
    {
        foreach ($this->menu() as $item) {
            if (($item['text'] ?? null) === $text) {
                return $item;
            }
        }

        return null;
    }
}
