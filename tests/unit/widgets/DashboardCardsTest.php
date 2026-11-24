<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\widgets;

use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\tests\_app\widgets\NotesStatWidget;
use Mitisk\Yii2Admin\tests\TestCase;
use Mitisk\Yii2Admin\widgets\IndexClearCacheWidget;
use Mitisk\Yii2Admin\widgets\IndexUserWidget;

/**
 * Карточки верхнего ряда главной: тренд, график по дням, встроенные виджеты.
 */
final class DashboardCardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Yii::$app->set('user', \Yii::$app->get('adminUser'));
        NotesStatWidget::$requiredPermission = 'admin';
        $this->loginAs(self::ADMIN_ID);
    }

    public function testCardShowsTrendAndDailyChart(): void
    {
        NotesStatWidget::$data = [
            'title' => 'Заметок', 'icon' => 'icon-file', 'value' => 12,
            'trend' => '+3 за неделю', 'tone' => 'green', 'spark' => [0, 2, 5, 1],
        ];

        $html = NotesStatWidget::widget();

        self::assertStringContainsString('+3 за неделю', $html);
        self::assertStringContainsString('db-stat__icon--green', $html);
        self::assertSame(4, substr_count($html, '<rect'), 'Столбик на каждый день');
    }

    public function testCardWithoutExtrasHasNoChartAndNoTrend(): void
    {
        NotesStatWidget::$data = ['title' => 'Заметок', 'icon' => 'icon-file', 'value' => 12];

        $html = NotesStatWidget::widget();

        self::assertStringNotContainsString('<svg', $html);
        self::assertStringNotContainsString('db-stat__trend', $html);
    }

    public function testCardEscapesTrendAndIgnoresUnknownTone(): void
    {
        NotesStatWidget::$data = [
            'title' => 'Заметок', 'icon' => 'icon-file', 'value' => 1,
            'trend' => '<b>+1</b>', 'tone' => '"><script>', 'spark' => ['x', -5, 3],
        ];

        $html = NotesStatWidget::widget();

        self::assertStringNotContainsString('<b>+1</b>', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertSame(3, substr_count($html, '<rect'), 'Нечисловые и отрицательные значения — нулевые столбики');
    }

    public function testUsersCardCountsUsersAndNewOnes(): void
    {
        $before = (int) AdminUser::find()->count();
        $this->createUser('ivan');
        $this->createUser('petr')->updateAttributes(['status' => AdminUser::STATUS_BLOCKED, 'created_at' => time() - 30 * 86400]);

        $html = IndexUserWidget::widget();

        self::assertStringContainsString('data-name="IndexUserWidget"', $html);
        self::assertStringContainsString('Пользователи', $html);
        self::assertStringContainsString('>' . ($before + 2) . '<', $html);
        self::assertStringContainsString('за неделю', $html);
        self::assertStringContainsString('заблокировано: 1', $html);
        self::assertStringContainsString('href="/admin/user/"', $html);
        self::assertSame(7, substr_count($html, '<rect'), 'Регистрации по дням за неделю');
    }

    public function testUsersCardIsHiddenWithoutPermission(): void
    {
        $user = $this->createUser('ivan', ['accessAdmin']);
        $this->loginAs((int) $user->id);

        self::assertSame('', IndexUserWidget::widget());
    }

    public function testCacheCardExplainsWhatItDoes(): void
    {
        $html = IndexClearCacheWidget::widget();

        self::assertStringContainsString('data-name="IndexClearCacheWidget"', $html);
        self::assertStringContainsString('id="btn-clear-cache"', $html);
        self::assertStringContainsString('Кэш сайта', $html);
        self::assertStringContainsString('не видны', $html);
    }
}
