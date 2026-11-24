<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\mail;

use Mitisk\Yii2Admin\components\MailService;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\EmailTemplate;
use Mitisk\Yii2Admin\tests\_app\mail\CapturingMailer;
use Mitisk\Yii2Admin\tests\_app\mail\ScriptedSmtpMailService;
use Mitisk\Yii2Admin\tests\_app\mail\ScriptedTransport;
use Mitisk\Yii2Admin\tests\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Yii;
use yii\symfonymailer\Message;

/**
 * Письма по шаблонам из админки (/admin/email-template/).
 */
final class MailServiceTest extends TestCase
{
    private const TO = 'client@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        MailService::resetConnection();
        CapturingMailer::$sent = [];
        ScriptedTransport::$calls = 0;
        ScriptedTransport::$outcomes = [];
        ScriptedSmtpMailService::$mailersCreated = 0;
        Yii::$app->settings->set('HIDDEN', 'mail_layout', '');
        Yii::$app->settings->set('Mitisk\Yii2Admin\models\MailTemplate', 'mailserver_login', 'robot@example.test');
        Yii::$app->settings->set('Mitisk\Yii2Admin\models\MailTemplate', 'mailserver_from_name', 'Сайт');
        $this->template('test_order', 'Заказ №{{ID}}', '<p>{{NAME}}, заказ {{ID}} принят. {{BUTTON}}</p>', [
            'ID' => ['desc' => 'Номер', 'required' => '1'],
            'NAME' => ['desc' => 'Имя', 'required' => '0'],
            'BUTTON' => ['desc' => 'Кнопка', 'required' => '0', 'html' => '1'],
        ]);
    }

    protected function tearDown(): void
    {
        MailService::resetConnection();
        parent::tearDown();
    }

    public function testValuesAreEscapedAndHtmlParamsKept(): void
    {
        $sent = $this->appMailService()->send('test_order', self::TO, [
            'ID' => 7,
            'NAME' => '<b>Аня</b>',
            'BUTTON' => '<a href="/orders/7">Открыть</a>',
        ]);

        self::assertTrue($sent);
        $html = $this->lastHtml();
        self::assertStringContainsString('&lt;b&gt;Аня&lt;/b&gt;', $html);
        self::assertStringContainsString('<a href="/orders/7">Открыть</a>', $html);
        self::assertSame('Заказ №7', CapturingMailer::$sent[0]->getSubject());
        self::assertSame(['robot@example.test' => 'Сайт'], CapturingMailer::$sent[0]->getFrom());
    }

    public function testLayoutWrapsBody(): void
    {
        Yii::$app->settings->set('HIDDEN', 'mail_layout', '<div class="layout">{{content}}</div>');

        $this->appMailService()->send('test_order', self::TO, ['ID' => 1]);

        self::assertStringStartsWith('<div class="layout"><p>', $this->lastHtml());
    }

    public function testMissingRequiredParamIsNotSent(): void
    {
        self::assertFalse($this->appMailService()->send('test_order', self::TO, ['NAME' => 'Аня']));
        self::assertSame([], CapturingMailer::$sent);
    }

    public function testInactiveTemplateIsNotSent(): void
    {
        EmailTemplate::updateAll(['active' => 0], ['slug' => 'test_order']);

        self::assertFalse($this->appMailService()->send('test_order', self::TO, ['ID' => 1]));
    }

    public function testSmtpConnectionIsReusedWithinRequest(): void
    {
        $service = new ScriptedSmtpMailService();

        self::assertTrue($service->send('test_order', self::TO, ['ID' => 1]));
        self::assertTrue((new ScriptedSmtpMailService())->send('test_order', self::TO, ['ID' => 2]));

        self::assertSame(1, ScriptedSmtpMailService::$mailersCreated);
        self::assertSame(2, ScriptedTransport::$calls);
    }

    public function testUnreachableSmtpIsNotRetriedWithinRequest(): void
    {
        ScriptedTransport::$outcomes = [new TransportException('Connection to smtp timed out for client@example.test')];
        $service = new ScriptedSmtpMailService();

        self::assertFalse($service->send('test_order', self::TO, ['ID' => 1]));
        self::assertFalse($service->send('test_order', self::TO, ['ID' => 2]));

        self::assertSame(1, ScriptedTransport::$calls, 'Второе письмо снова ждало таймаут SMTP');
    }

    public function testRejectedRecipientDoesNotBlockNextEmail(): void
    {
        ScriptedTransport::$outcomes = [new UnexpectedResponseException('550 No such user client@example.test')];
        $service = new ScriptedSmtpMailService();

        self::assertFalse($service->send('test_order', self::TO, ['ID' => 1]));
        self::assertTrue($service->send('test_order', 'other@example.test', ['ID' => 2]));
        self::assertSame(2, ScriptedSmtpMailService::$mailersCreated, 'После отказа нужно чистое соединение');
    }

    public function testFailureLogHasNoRecipientAddress(): void
    {
        ScriptedTransport::$outcomes = [new UnexpectedResponseException('550 No such user client@example.test')];
        Yii::getLogger()->messages = [];

        (new ScriptedSmtpMailService())->send('test_order', self::TO, ['ID' => 1]);

        $logged = implode("\n", array_map(
            static fn (array $m): string => is_string($m[0]) ? $m[0] : print_r($m[0], true),
            Yii::getLogger()->messages
        ));
        self::assertStringContainsString('test_order', $logged);
        self::assertStringNotContainsString('client@example.test', $logged);
    }

    public function testWithoutSmtpHostReturnsFalseWithoutException(): void
    {
        Yii::$app->settings->set('Mitisk\Yii2Admin\models\MailTemplate', 'mailserver_host', '');

        self::assertFalse((new MailService())->send('test_order', self::TO, ['ID' => 1]));
    }

    public function testNewPasswordEmailRespectsContainerConfig(): void
    {
        Yii::$container->set(MailService::class, ['mailer' => ['class' => CapturingMailer::class]]);
        try {
            $this->template('test_new_password', 'Новый пароль', '<p>{{PASSWORD}}</p>', [
                'PASSWORD' => ['desc' => 'Пароль', 'required' => '1'],
            ]);
            Yii::$app->settings->set(AdminUser::class, 'mail_template_new_password', 'test_new_password');
            $user = $this->createUser('newbie');
            $user->password = 'S3cret<1>';
            $user->send_new_password = '1';

            self::assertTrue($user->saveUser(), print_r($user->getErrors(), true));
        } finally {
            Yii::$container->clear(MailService::class);
        }

        self::assertCount(1, CapturingMailer::$sent);
        self::assertStringContainsString('S3cret&lt;1&gt;', $this->lastHtml());
    }

    private function appMailService(): MailService
    {
        return new MailService(['mailer' => ['class' => CapturingMailer::class]]);
    }

    private function lastHtml(): string
    {
        $message = end(CapturingMailer::$sent);
        self::assertInstanceOf(Message::class, $message);

        return (string) $message->getSymfonyEmail()->getHtmlBody();
    }

    /**
     * @param array<string, array<string, string>> $params
     */
    private function template(string $slug, string $subject, string $body, array $params): void
    {
        $template = EmailTemplate::findOne(['slug' => $slug]) ?? new EmailTemplate(['slug' => $slug]);
        $template->name = $slug;
        $template->subject = $subject;
        $template->body = $body;
        $template->params = $params;
        $template->active = 1;
        self::assertTrue($template->save(), print_r($template->getErrors(), true));
    }
}
