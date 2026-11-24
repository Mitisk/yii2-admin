<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\widgets;

use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\AdminWidget;
use Mitisk\Yii2Admin\tests\_app\widgets\NotesStatWidget;
use Mitisk\Yii2Admin\tests\TestCase;
use Mitisk\Yii2Admin\widgets\IndexUserWidget;

/**
 * Главная: добавление, скрытие и возврат виджетов верхнего ряда.
 */
final class DashboardWidgetsTest extends TestCase
{
    public function testClassThatIsNotAWidgetIsNeverInstantiated(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $result = $this->add(AdminUser::class);

        self::assertFalse($result['success']);
        self::assertSame(0, (int) AdminWidget::find()->where(['user_id' => self::ADMIN_ID])->count());
    }

    public function testOrdinaryUserAddsOnlyRegisteredWidgets(): void
    {
        $user = $this->createUser('ivan', ['accessAdmin']);
        $this->loginAs((int) $user->id);

        $result = $this->add(NotesStatWidget::class);

        self::assertFalse($result['success'], 'Виджет не зарегистрирован в админке: любой класс проекта так не подключить');
        self::assertSame(0, (int) AdminWidget::find()->where(['user_id' => $user->id])->count());
    }

    public function testSuperAdminAddsWidgetByClass(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $result = $this->add(NotesStatWidget::class);

        self::assertTrue($result['success'], (string) ($result['message'] ?? ''));
        self::assertSame(1, (int) AdminWidget::find()->where(['user_id' => self::ADMIN_ID, 'published' => 1])->count());
    }

    public function testHiddenWidgetIsOfferedAndReturnsWithoutDuplicate(): void
    {
        $user = $this->createUser('ivan', ['accessAdmin', 'viewUsers']);
        $this->loginAs((int) $user->id);

        $hidden = $this->runAction('admin/ajax-widget/hide', [], ['alias' => 'IndexUserWidget']);
        self::assertTrue($hidden['success']);

        $page = $this->runAction('admin/default/index');
        self::assertIsString($page);
        self::assertStringNotContainsString('data-name="IndexUserWidget"', $page, 'Скрытый виджет остался на главной');
        self::assertStringContainsString('data-widget-class="' . ltrim(IndexUserWidget::class, '\\') . '"', $page, 'Скрытый виджет не предложен к возврату');
        self::assertStringContainsString('Пользователи', $page);

        $result = $this->add(IndexUserWidget::class);

        self::assertTrue($result['success'], (string) ($result['message'] ?? ''));
        self::assertStringContainsString('data-name="IndexUserWidget"', $result['html']);
        $rows = AdminWidget::find()->where(['user_id' => $user->id, 'alias' => 'IndexUserWidget'])->all();
        self::assertCount(1, $rows, 'Возврат виджета создал вторую запись');
        self::assertSame(1, (int) $rows[0]->published);
    }

    public function testWidgetAlreadyOnDashboardIsNotOffered(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $page = $this->runAction('admin/default/index');

        self::assertIsString($page);
        self::assertStringNotContainsString('data-widget-class="' . ltrim(IndexUserWidget::class, '\\') . '"', $page);
    }

    public function testWidgetAddedTwiceStaysSingle(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $this->add(NotesStatWidget::class);
        $this->add(NotesStatWidget::class);

        self::assertSame(1, (int) AdminWidget::find()->where(['user_id' => self::ADMIN_ID, 'alias' => 'NotesStatWidget'])->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function add(string $class): array
    {
        $result = $this->runAction('admin/ajax-widget/add', [], ['class' => $class]);
        self::assertIsArray($result);

        return $result;
    }
}
