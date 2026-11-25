<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\mail;

use Mitisk\Yii2Admin\components\mail\TemplateParams;
use Mitisk\Yii2Admin\components\MailService;
use Mitisk\Yii2Admin\models\EmailTemplate;
use Mitisk\Yii2Admin\tests\_app\mail\CapturingMailer;
use Mitisk\Yii2Admin\tests\_app\mail\TestMailEvent;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\symfonymailer\Message;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * Редактор шаблона письма (/admin/email-template/update/): переменные, предпросмотр, тестовая отправка.
 */
final class EmailTemplateEditorTest extends TestCase
{
    private const SMTP = 'Mitisk\Yii2Admin\models\MailTemplate';

    protected function setUp(): void
    {
        parent::setUp();
        CapturingMailer::$sent = [];
        MailService::resetConnection();
        Yii::$app->settings->set('HIDDEN', 'mail_layout', '');
        Yii::$app->settings->set(self::SMTP, 'mailserver_login', 'robot@example.test');
        Yii::$app->settings->set(self::SMTP, 'mailserver_from_name', 'Сайт');
        Yii::$container->set(MailService::class, ['mailer' => ['class' => CapturingMailer::class]]);
        EmailTemplate::deleteAll(['slug' => TestMailEvent::OrderPaid->slug()]);
        $this->loginAs(self::ADMIN_ID);
    }

    protected function tearDown(): void
    {
        Yii::$container->clear(MailService::class);
        MailService::resetConnection();
        parent::tearDown();
    }

    public function testVariableUsedOnlyInSubjectKeepsItsSettings(): void
    {
        $template = $this->template('test_editor', 'Заказ №{{ID}}', '<p>Спасибо!</p>');

        $response = $this->save($template, [
            'subject' => 'Заказ №{{ID}}',
            'body' => '<p>Спасибо!</p>',
            'params' => ['ID' => ['desc' => 'Номер заказа', 'sample' => '1042', 'required' => '1', 'html' => '0']],
        ]);

        self::assertInstanceOf(Response::class, $response);
        self::assertStringContainsString('/admin/email-template/update/', (string) $response->headers->get('Location'), 'После сохранения остаёмся в редакторе');
        $template->refresh();
        // JSON-колонка MySQL хранит ключи в своём порядке — сравниваем без него
        self::assertEquals(
            ['ID' => ['desc' => 'Номер заказа', 'required' => '1', 'html' => '0', 'sample' => '1042']],
            $template->params
        );
    }

    public function testVariableTypedInTextIsStoredEvenWithoutRowInForm(): void
    {
        $template = $this->template('test_editor', 'Тема', '<p>Текст</p>');

        $this->save($template, ['subject' => 'Тема', 'body' => '<p>{{NAME}}, здравствуйте</p>']);

        $template->refresh();
        self::assertSame(['NAME'], array_keys($template->params));
    }

    public function testUnusedVariableIsKeptOnlyIfItIsDescribed(): void
    {
        $template = $this->template('test_editor', 'Тема', '<p>Текст</p>');

        $this->save($template, ['subject' => 'Тема', 'body' => '<p>Текст</p>', 'params' => [
            'TYPO' => ['desc' => '', 'sample' => ''],
            'LATER' => ['desc' => 'Понадобится позже', 'sample' => ''],
            'bad name!' => ['desc' => 'Имя с пробелом'],
        ]]);

        $template->refresh();
        self::assertSame(['LATER'], array_keys($template->params));
    }

    public function testEventTemplateKeepsCodeVariablesFlagsAndCode(): void
    {
        Yii::$app->getModule('admin')->mailEvents = [TestMailEvent::class];
        $template = $this->template(TestMailEvent::OrderPaid->slug(), 'Заказ', '<p>Оплачен</p>');

        // Владелец убрал переменные из текста, снял флажки и попробовал сменить код
        $this->save($template, ['slug' => 'renamed', 'subject' => 'Заказ', 'body' => '<p>Оплачен</p>', 'params' => [
            'ID' => ['desc' => '', 'required' => '0', 'html' => '0', 'sample' => '77'],
            'BUTTON' => ['desc' => 'Моя кнопка', 'required' => '0', 'html' => '0'],
        ]]);

        $template->refresh();
        self::assertSame(TestMailEvent::OrderPaid->slug(), $template->slug, 'Код письма-события из формы не меняется');
        self::assertSame(['ID', 'NAME', 'BUTTON'], array_keys($template->params), 'Переменные из кода остаются, даже если их нет в тексте');
        self::assertSame('1', $template->params['ID']['required']);
        self::assertSame('Номер заказа', $template->params['ID']['desc'], 'Пустое описание — берётся из кода');
        self::assertSame('77', $template->params['ID']['sample']);
        self::assertSame('1', $template->params['BUTTON']['html']);
        self::assertSame('Моя кнопка', $template->params['BUTTON']['desc']);
    }

    public function testEditorPageShowsVariablesAndEventTools(): void
    {
        Yii::$app->getModule('admin')->mailEvents = [TestMailEvent::class];
        $template = $this->template(TestMailEvent::OrderPaid->slug(), 'Заказ №{{ID}}', '<p>{{NAME}} {{EXTRA}}</p>');

        $html = (string) $this->runAction('admin/email-template/update', ['id' => $template->id]);

        foreach (['ID', 'NAME', 'BUTTON', 'EXTRA'] as $name) {
            self::assertStringContainsString('data-et-var="' . $name . '"', $html);
        }
        self::assertStringContainsString('Вернуть исходный текст', $html);
        self::assertStringContainsString('Код задан в коде сайта', $html);
        self::assertStringNotContainsString('data-et-slug-unlock', $html);
        self::assertStringContainsString('Сайт <span>&lt;robot@example.test&gt;</span>', $html);
        self::assertStringContainsString('value="Кнопка «Открыть заказ»"', $html, 'Описание переменной — из кода');
    }

    public function testPreviewShowsEditorTextWithSamplesInLayout(): void
    {
        Yii::$app->settings->set('HIDDEN', 'mail_layout', '<div class="layout">{{content}}<footer>{{SITE}}</footer></div>');

        $preview = $this->runAction('admin/email-template/preview', [], [
            'subject' => 'Заказ №{{ID}}',
            'body' => '<p>{{NAME}}, {{BUTTON}} {{UNKNOWN}}</p>',
            'params' => [
                'ID' => ['sample' => '1042'],
                'NAME' => ['desc' => 'Имя клиента', 'sample' => '<b>Аня</b>'],
                'BUTTON' => ['desc' => 'Кнопка', 'html' => '1'],
            ],
        ]);

        self::assertSame('Заказ №1042', $preview['subject']);
        self::assertTrue($preview['hasLayout']);
        self::assertStringStartsWith('<div class="layout"><p>&lt;b&gt;Аня&lt;/b&gt;, <a href="#">Кнопка</a> [UNKNOWN]</p>', $preview['html']);
        self::assertStringContainsString('<footer>[SITE]</footer>', $preview['html'], 'Переменная макета тоже получает пример');
    }

    public function testTestLetterCarriesUnsavedEditorText(): void
    {
        $result = $this->runAction('admin/email-template/test-send', [], [
            'email' => 'owner@example.test',
            'subject' => 'Заказ №{{ID}}',
            'body' => '<p>Новый текст, {{NAME}}</p>',
            'params' => ['ID' => ['sample' => '5'], 'NAME' => ['desc' => 'Имя']],
        ]);

        self::assertTrue($result['success'], $result['message']);
        self::assertCount(1, CapturingMailer::$sent);
        $message = CapturingMailer::$sent[0];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('Тест: Заказ №5', $message->getSubject());
        self::assertSame(['owner@example.test' => ''], array_map('strval', (array) $message->getTo()));
        self::assertStringContainsString('<p>Новый текст, [Имя]</p>', (string) $message->getSymfonyEmail()->getHtmlBody());
    }

    public function testTestLetterNeedsAddressTextAndMailServer(): void
    {
        $bad = $this->runAction('admin/email-template/test-send', [], ['email' => 'не адрес', 'subject' => 'Тема']);
        self::assertFalse($bad['success']);

        $empty = $this->runAction('admin/email-template/test-send', [], ['email' => 'owner@example.test']);
        self::assertFalse($empty['success']);

        Yii::$container->clear(MailService::class);
        Yii::$app->settings->set(self::SMTP, 'mailserver_host', '');
        $noServer = $this->runAction('admin/email-template/test-send', [], ['email' => 'owner@example.test', 'subject' => 'Тема']);
        self::assertFalse($noServer['success']);
        self::assertStringContainsString('Почтовый сервер не настроен', $noServer['message']);
        self::assertCount(0, CapturingMailer::$sent);
    }

    public function testListCreatesTemplatesOfEventsNotSentYet(): void
    {
        Yii::$app->getModule('admin')->mailEvents = [TestMailEvent::class];

        $this->runAction('admin/email-template/index');

        $template = EmailTemplate::findOne(['slug' => TestMailEvent::OrderPaid->slug()]);
        self::assertNotNull($template);
        self::assertSame('Заказ оплачен', $template->name);
        self::assertSame(TestMailEvent::OrderPaid->defaultBody(), $template->body);
    }

    public function testDeleteIsNotAvailableByLink(): void
    {
        $template = $this->template('test_editor', 'Тема', '<p>Текст</p>');

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->runAction('admin/email-template/delete', ['id' => $template->id]);
    }

    public function testSamplesFallBackToDescriptionOrName(): void
    {
        $samples = TemplateParams::samples(
            ['NAME' => ['desc' => 'Имя'], 'LINK' => ['desc' => 'Ссылка <на> заказ', 'html' => '1'], 'ID' => ['sample' => '9']],
            ['NAME', 'LINK', 'ID', 'OTHER']
        );

        self::assertSame('[Имя]', $samples['NAME']);
        self::assertSame('<a href="#">Ссылка &lt;на&gt; заказ</a>', $samples['LINK']);
        self::assertSame('9', $samples['ID']);
        self::assertSame('[OTHER]', $samples['OTHER']);
        self::assertSame(['A', 'B'], TemplateParams::names('{{A}} {{content}}', '<a href="%7B%7BB%7D%7D">{{A}}</a>'));
    }

    private function template(string $slug, string $subject, string $body): EmailTemplate
    {
        $template = EmailTemplate::findOne(['slug' => $slug]) ?? new EmailTemplate(['slug' => $slug]);
        $template->name = 'Тестовый шаблон';
        $template->subject = $subject;
        $template->body = $body;
        $template->params = [];
        $template->active = 1;
        self::assertTrue($template->save(), print_r($template->getErrors(), true));

        return $template;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function save(EmailTemplate $template, array $fields): mixed
    {
        return $this->runAction('admin/email-template/update', ['id' => $template->id], [
            'EmailTemplate' => $fields + ['name' => $template->name, 'slug' => $template->slug, 'active' => '1'],
        ]);
    }
}
