<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\mail;

use Mitisk\Yii2Admin\components\MailService;
use Mitisk\Yii2Admin\models\EmailTemplate;
use Mitisk\Yii2Admin\tests\_app\mail\CapturingMailer;
use Mitisk\Yii2Admin\tests\_app\mail\TestMailEvent;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Письма-события из кода: шаблон создаётся из текстов в коде, владелец правит его в админке.
 */
final class MailEventTest extends TestCase
{
    private const TO = 'client@example.test';

    private MailService $mail;

    protected function setUp(): void
    {
        parent::setUp();
        CapturingMailer::$sent = [];
        Yii::$app->settings->set('HIDDEN', 'mail_layout', '');
        EmailTemplate::deleteAll(['slug' => TestMailEvent::OrderPaid->slug()]);
        $this->mail = new MailService(['mailer' => ['class' => CapturingMailer::class]]);
    }

    public function testMissingTemplateIsCreatedFromCodeAndSent(): void
    {
        self::assertTrue($this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 7, 'NAME' => 'Аня']));

        $template = EmailTemplate::findOne(['slug' => 'test_order_paid']);
        self::assertNotNull($template, 'Шаблон не создан');
        self::assertSame('Заказ оплачен', $template->name);
        self::assertSame(1, (int) $template->active);
        self::assertSame('1', $template->params['BUTTON']['html']);
        self::assertSame('Заказ №7 оплачен', CapturingMailer::$sent[0]->getSubject());
    }

    public function testTemplateEditedByOwnerWins(): void
    {
        $this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 1]);
        EmailTemplate::updateAll(['subject' => 'Оплата заказа {{ID}} получена'], ['slug' => 'test_order_paid']);

        $this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 2]);

        self::assertSame('Оплата заказа 2 получена', CapturingMailer::$sent[1]->getSubject());
    }

    public function testDisabledTemplateIsNotSent(): void
    {
        $this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 1]);
        EmailTemplate::updateAll(['active' => 0], ['slug' => 'test_order_paid']);

        self::assertFalse($this->mail->isEnabled(TestMailEvent::OrderPaid));
        self::assertFalse($this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 2]));
        self::assertCount(1, CapturingMailer::$sent);
    }

    public function testEventWithoutTemplateIsEnabled(): void
    {
        self::assertTrue($this->mail->isEnabled(TestMailEvent::OrderPaid));
    }

    public function testRequiredParamFromCodeIsChecked(): void
    {
        self::assertFalse($this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['NAME' => 'Аня']));
        self::assertSame([], CapturingMailer::$sent);
    }

    public function testHtmlParamFromCodeIsKeptEvenIfOwnerUnticksIt(): void
    {
        $this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 1]);
        $template = EmailTemplate::findOne(['slug' => 'test_order_paid']);
        $params = $template->params;
        unset($params['BUTTON']['html']);
        $template->params = $params;
        self::assertTrue($template->save());

        $this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 2, 'BUTTON' => '<a href="/o/2">Открыть</a>']);

        $html = (string) CapturingMailer::$sent[1]->getSymfonyEmail()->getHtmlBody();
        self::assertStringContainsString('<a href="/o/2">Открыть</a>', $html);
    }

    public function testEmptiedBodyFallsBackToCodeText(): void
    {
        $this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 1]);
        EmailTemplate::updateAll(['body' => ''], ['slug' => 'test_order_paid']);

        $this->mail->sendEvent(TestMailEvent::OrderPaid, self::TO, ['ID' => 2, 'NAME' => 'Аня']);

        self::assertStringContainsString('Аня, спасибо!', (string) CapturingMailer::$sent[1]->getSymfonyEmail()->getHtmlBody());
    }
}
