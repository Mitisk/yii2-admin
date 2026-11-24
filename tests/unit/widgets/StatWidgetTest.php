<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\widgets;

use Mitisk\Yii2Admin\tests\_app\widgets\NotesStatWidget;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Карточка статистики на дашборде: подкласс отдаёт данные, вёрстка и меню карточки — из пакета.
 */
final class StatWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->set('user', Yii::$app->get('adminUser'));
        NotesStatWidget::$requiredPermission = 'admin';
        NotesStatWidget::$data = [
            'title' => 'Заметок за неделю',
            'icon' => 'icon-file-text',
            'value' => 1234,
            'note' => 'из них 5 черновиков',
            'url' => '/admin/test-notes/',
        ];
    }

    public function testRendersDashboardCard(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $html = NotesStatWidget::widget();

        self::assertStringContainsString('dashboard-top-widget-item', $html);
        self::assertStringContainsString('data-name="NotesStatWidget"', $html);
        self::assertStringContainsString('dashboard-top-widget-item-hide', $html, 'Нет меню «Скрыть»');
        self::assertStringContainsString('Заметок за неделю', $html);
        self::assertStringContainsString('1 234', $html);
        self::assertStringContainsString('из них 5 черновиков', $html);
        self::assertStringContainsString('href="/admin/test-notes/"', $html);
        self::assertStringContainsString('icon-file-text', $html);
    }

    public function testEscapesTexts(): void
    {
        $this->loginAs(self::ADMIN_ID);
        NotesStatWidget::$data['title'] = '<b>Заметки</b>';

        $html = NotesStatWidget::widget();

        self::assertStringNotContainsString('<b>Заметки</b>', $html);
        self::assertStringContainsString('&lt;b&gt;Заметки&lt;/b&gt;', $html);
    }

    public function testCardWithoutLinkAndNote(): void
    {
        $this->loginAs(self::ADMIN_ID);
        NotesStatWidget::$data = ['title' => 'Заметок', 'icon' => 'icon-file', 'value' => '—'];

        $html = NotesStatWidget::widget();

        self::assertStringContainsString('Заметок', $html);
        self::assertStringNotContainsString('<a href', $html);
    }

    public function testHiddenFromUserWithoutPermission(): void
    {
        $this->loginAs($this->createUser('editor')->id);

        self::assertSame('', NotesStatWidget::widget());
    }

    public function testPermissionCanBeOpenedToAnyUser(): void
    {
        NotesStatWidget::$requiredPermission = null;
        $this->loginAs($this->createUser('editor')->id);

        self::assertStringContainsString('Заметок за неделю', NotesStatWidget::widget());
    }
}
