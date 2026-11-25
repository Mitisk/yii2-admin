<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\waf;

use Mitisk\Waf\WafControl;
use Mitisk\Yii2Admin\components\WafBridge;
use Mitisk\Yii2Admin\tests\TestCase;
use Mitisk\Yii2Admin\widgets\MenuWidget;
use Mitisk\Yii2Admin\widgets\WafStatWidget;
use Yii;
use yii\helpers\FileHelper;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Раздел «Защита»: управление пакетом mitisk/yii2-lightweight-waf из админки.
 * Есть пакет — раздел появляется сам; нет — админка работает как раньше.
 */
final class WafSectionTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        if (!class_exists(WafControl::class)) {
            self::markTestSkipped('Пакет mitisk/yii2-lightweight-waf не установлен.');
        }
        parent::setUp();
        $this->storageDir = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . 'waf_blocks';
        FileHelper::removeDirectory($this->storageDir);
        WafBridge::$entryClass = \Mitisk\Waf\LightweightWaf::class;
        Yii::$app->set('user', Yii::$app->get('adminUser'));
    }

    /** Блокировка «по шаблону», как её ставит сам WAF; $age — на сколько секунд она свежее прочих. */
    private function automaticBlock(string $ip, int $age = 0): void
    {
        $storage = new \Mitisk\Waf\Storage($this->storageDir);
        $now = time();
        $storage->saveBlock([
            'key' => $ip, 'ip' => $ip, 'at' => $now + $age, 'until' => $now + 86400 + $age,
            'uri' => '/wp-login.php', 'pattern' => '/wp-login', 'reason' => 'pattern',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->storageDir)) {
            FileHelper::removeDirectory($this->storageDir);
            WafBridge::$entryClass = \Mitisk\Waf\LightweightWaf::class;
        }
        parent::tearDown();
    }

    public function testMenuItemAppearsOnlyWhenWafIsInstalled(): void
    {
        $this->loginAs(self::ADMIN_ID);

        self::assertStringContainsString('/admin/waf/', MenuWidget::widget());

        WafBridge::$entryClass = 'No\\Such\\Waf';
        self::assertStringNotContainsString('/admin/waf/', MenuWidget::widget());
    }

    public function testSectionIs404WithoutWaf(): void
    {
        $this->loginAs(self::ADMIN_ID);
        WafBridge::$entryClass = 'No\\Such\\Waf';

        $this->expectException(NotFoundHttpException::class);
        $this->runAction('admin/waf/index');
    }

    public function testSectionIsForAdminsOnly(): void
    {
        $this->loginAs($this->createUser('editor')->id);

        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('admin/waf/index');
    }

    public function testPageShowsSettingsBlocksAndJournal(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->control()->block('203.0.113.9', 3600, 'спам');

        $html = (string) $this->runAction('admin/waf/index');

        self::assertStringContainsString('203.0.113.9', $html);
        self::assertStringContainsString('/wp-login', $html, 'Встроенные шаблоны перечислены');
        self::assertStringContainsString('Waf[blockHours]', $html);
        self::assertStringContainsString('не подключён', $html, 'В тестах run() не вызывался — есть подсказка');
    }

    public function testWarnsWhenSiteIsBehindProxyThatWafDoesNotTrust(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';

        self::assertStringNotContainsString('за прокси', (string) $this->runAction('admin/waf/index'));

        // Заголовок прокси есть, а WAF им не пользуется: все посетители для него — один адрес
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
        try {
            $html = (string) $this->runAction('admin/waf/index');
        } finally {
            unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
        }

        self::assertStringContainsString('за прокси', $html);
        self::assertStringContainsString('10.0.0.2', $html);
    }

    public function testSavingWritesWafFileAndKeepsCopyInDatabase(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $response = $this->runAction('admin/waf/save', [], ['Waf' => [
            'enabled' => '1',
            'observeOnly' => '1',
            'permanentBlock' => '0',
            'blockHours' => '2',
            'enabledPatterns' => ['/wp-login', '/.env'],
            'basePatterns' => ['/wp-login', '/.env', '/login.php'],
            'extraPatterns' => "/old-cms/\n/secret-backup",
            'allowIps' => "203.0.113.0/24\n",
            'recaptchaSiteKey' => '',
            'recaptchaSecret' => '',
            'captchaMaxAttempts' => '5',
            'captchaWindowMinutes' => '10',
            'skipCrossSite' => '1',
            'verifyBots' => '1',
        ]]);

        self::assertInstanceOf(Response::class, $response);
        $saved = $this->control()->settings();
        self::assertTrue($saved['observeOnly']);
        self::assertSame(7200, $saved['blockDuration']);
        self::assertSame(['/login.php'], $saved['disabledPatterns']);
        self::assertSame(['/old-cms/', '/secret-backup'], $saved['extraPatterns']);
        self::assertSame(['203.0.113.0/24'], $saved['allowIps']);
        self::assertSame(600, $saved['captchaWindow']);
        self::assertSame($saved, WafBridge::storedSettings(), 'Копия в базе совпадает с файлом');
        self::assertNotEmpty(Yii::$app->session->getFlash('success'));
    }

    public function testInvalidSettingsAreNotSaved(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $this->runAction('admin/waf/save', [], ['Waf' => ['allowIps' => 'офис', 'extraPatterns' => '/adm']]);

        self::assertSame([], $this->control()->settings());
        self::assertSame([], WafBridge::storedSettings());
        $error = implode(' ', (array) Yii::$app->session->getFlash('error'));
        self::assertStringContainsString('офис', $error);
        self::assertStringContainsString('/adm', $error, 'Шаблон, блокирующий саму админку, отклонён');
    }

    public function testLostSettingsFileIsRestoredFromDatabase(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->runAction('admin/waf/save', [], ['Waf' => ['observeOnly' => '1']]);
        FileHelper::removeDirectory($this->storageDir);

        self::assertTrue(WafBridge::heal());

        self::assertTrue($this->control()->settings()['observeOnly']);
        self::assertFalse(WafBridge::heal(), 'Повторно восстанавливать нечего');
    }

    public function testResetRemovesFileAndCopy(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->runAction('admin/waf/save', [], ['Waf' => ['observeOnly' => '1']]);

        $this->runAction('admin/waf/reset', [], []);

        self::assertSame([], $this->control()->settings());
        self::assertSame([], WafBridge::storedSettings());
    }

    public function testUnblockAndManualBlock(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->control()->block('203.0.113.9', 3600);

        $this->runAction('admin/waf/unblock', [], ['key' => '203.0.113.9']);
        self::assertSame([], $this->control()->blocks());

        $this->runAction('admin/waf/block', [], ['ip' => '203.0.113.10', 'hours' => '0', 'note' => 'спам в формах']);
        $block = $this->control()->blocks()[0];
        self::assertSame('203.0.113.10', $block['ip']);
        self::assertSame(0, $block['until'], '0 часов — бессрочно');

        $this->runAction('admin/waf/block', [], ['ip' => 'не адрес', 'hours' => '1']);
        self::assertCount(1, $this->control()->blocks());
        self::assertNotEmpty(Yii::$app->session->getFlash('error'));
    }

    public function testAllowIpAddsToWhiteListAndUnblocks(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->runAction('admin/waf/save', [], ['Waf' => ['allowIps' => '10.0.0.0/8']]);
        $this->control()->block('203.0.113.9', 3600);

        $this->runAction('admin/waf/allow-ip', [], ['ip' => '203.0.113.9']);

        self::assertSame(['10.0.0.0/8', '203.0.113.9'], $this->control()->settings()['allowIps']);
        self::assertSame([], $this->control()->blocks());
    }

    public function testUnblockAllKeepsManualBlocksUnlessAsked(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $control = WafBridge::control();
        $control->block('203.0.113.9', 3600, 'вручную');
        $this->automaticBlock('203.0.113.10');
        $this->automaticBlock('203.0.113.11');

        $this->runAction('admin/waf/unblock-all', [], []);

        self::assertSame(1, $control->blocksCount(), 'Ручная блокировка снята без спроса');

        $this->runAction('admin/waf/unblock-all', [], ['manual' => '1']);

        self::assertSame(0, $control->blocksCount());
    }

    public function testBlocksAreShownByPagesWithTotal(): void
    {
        $this->loginAs(self::ADMIN_ID);
        for ($i = 1; $i <= 55; $i++) {
            $this->automaticBlock('203.0.113.' . $i, $i);
        }

        $first = $this->runAction('admin/waf/index');
        $second = $this->runAction('admin/waf/index', ['blocksPage' => 2]);

        self::assertIsString($first);
        self::assertStringContainsString('Заблокированные (55)', $first);
        self::assertStringContainsString('203.0.113.55', $first, 'На первой странице — самые свежие');
        self::assertStringNotContainsString('>203.0.113.1<', $first);
        self::assertStringContainsString('blocksPage=2', $first, 'Нет ссылки на следующую страницу');
        self::assertIsString($second);
        self::assertStringContainsString('>203.0.113.1<', $second);
    }

    public function testBlockLimitIsSavedFromForm(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $this->runAction('admin/waf/save', [], ['Waf' => ['maxBlocks' => '750']]);

        self::assertSame(750, WafBridge::control()->settings()['maxBlocks']);
    }

    public function testPermanentModeIsExplainedOnThePage(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->runAction('admin/waf/save', [], ['Waf' => ['permanentBlock' => '1']]);

        $html = $this->runAction('admin/waf/index');

        self::assertIsString($html);
        self::assertStringContainsString('Бессрочные блокировки не снимаются сами', $html);
    }

    public function testDashboardWidgetShowsWeeklyBans(): void
    {
        $this->loginAs(self::ADMIN_ID);
        $this->control()->block('203.0.113.9', 3600);

        $html = WafStatWidget::widget();

        self::assertStringContainsString('data-name="WafStatWidget"', $html);
        self::assertStringContainsString('/admin/waf/', $html);

        WafBridge::$entryClass = 'No\\Such\\Waf';
        self::assertSame('', WafStatWidget::widget(), 'Без WAF карточки нет');
    }

    private function control(): WafControl
    {
        return new WafControl($this->storageDir);
    }
}
