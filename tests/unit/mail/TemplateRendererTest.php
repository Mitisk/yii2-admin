<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\mail;

use Mitisk\Yii2Admin\components\mail\TemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Подстановка переменных {{ИМЯ}} в письмо: значения экранируются, макет оборачивает тело.
 */
final class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TemplateRenderer();
    }

    public function testValuesAreEscapedInBody(): void
    {
        $email = $this->renderer->render('Привет', '<p>{{NAME}}</p>', ['NAME' => '<a href="//evil">Аня</a>'], [], null);

        self::assertSame('<p>&lt;a href=&quot;//evil&quot;&gt;Аня&lt;/a&gt;</p>', $email->html);
    }

    public function testHtmlParamsAreInsertedAsIs(): void
    {
        $email = $this->renderer->render('', '{{BUTTON}}', ['BUTTON' => '<a href="/x">Войти</a>'], ['BUTTON'], null);

        self::assertSame('<a href="/x">Войти</a>', $email->html);
    }

    public function testLineBreaksInValuesBecomeBr(): void
    {
        $email = $this->renderer->render('', '{{TEXT}}', ['TEXT' => "Строка 1\nСтрока 2"], [], null);

        self::assertSame("Строка 1<br />\nСтрока 2", $email->html);
    }

    public function testMissingVariablesBecomeEmpty(): void
    {
        $email = $this->renderer->render('Заказ {{ID}}', '<p>{{NAME}}{{UNKNOWN}}</p>', ['ID' => 5], [], null);

        self::assertSame('Заказ 5', $email->subject);
        self::assertSame('<p></p>', $email->html);
    }

    public function testValuesAreNotExpandedTwice(): void
    {
        $email = $this->renderer->render('', '{{A}}', ['A' => '{{B}}', 'B' => 'секрет'], [], null);

        self::assertSame('{{B}}', $email->html);
    }

    public function testPlaceholderEncodedByVisualEditorInLink(): void
    {
        $email = $this->renderer->render('', '<a href="%7B%7BURL%7D%7D">ссылка</a>', ['URL' => 'https://site/x?a=1&b=2'], [], null);

        self::assertSame('<a href="https://site/x?a=1&amp;b=2">ссылка</a>', $email->html);
    }

    public function testBodyIsWrappedIntoLayout(): void
    {
        $email = $this->renderer->render('', '<p>{{NAME}}</p>', ['NAME' => 'Аня'], [], '<div class="mail">{{content}}</div>');

        self::assertSame('<div class="mail"><p>Аня</p></div>', $email->html);
    }

    public function testLayoutWithoutContentMarkerIsIgnored(): void
    {
        $email = $this->renderer->render('', '<p>тело</p>', [], [], '<div>сломанный макет</div>');

        self::assertSame('<p>тело</p>', $email->html);
    }

    public function testSubjectIsOneLineWithoutEscaping(): void
    {
        $email = $this->renderer->render("Заказ  {{ID}}\r\nBcc: x@evil", '', ['ID' => '<5> & Co'], [], null);

        self::assertSame('Заказ <5> & Co Bcc: x@evil', $email->subject);
    }

    public function testSubjectIsLimitedTo255Characters(): void
    {
        $email = $this->renderer->render('{{T}}', '', ['T' => str_repeat('я', 300)], [], null);

        self::assertSame(255, mb_strlen($email->subject));
    }
}
