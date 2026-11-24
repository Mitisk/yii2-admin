<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\pages;

use Mitisk\Yii2Admin\models\Page;
use Mitisk\Yii2Admin\models\PageRedirect;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\web\Response;

/**
 * Адреса страниц раздела «Контент» учитывают suffix urlManager сайта
 * (в тестовом приложении, как в инструкции пакета, suffix = '/').
 */
final class PageUrlTest extends TestCase
{
    public function testPageUrlEndsWithSiteSuffix(): void
    {
        $page = $this->createPage('about');

        self::assertSame('/about/', Yii::$app->pages->url($page));
        self::assertSame('/about/', $page->getUrl());
    }

    public function testAddressWithoutSuffixRedirectsToCanonical(): void
    {
        $this->createPage('about');

        $response = $this->open('about');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(301, $response->statusCode);
        self::assertSame('/about/', $this->location($response));
    }

    public function testCanonicalAddressShowsPage(): void
    {
        $this->createPage('about', 'О нас');

        $html = $this->open('about/');

        self::assertIsString($html);
        self::assertStringContainsString('О нас', $html);
    }

    public function testOldAddressRedirectsToNewWithSuffix(): void
    {
        $page = $this->createPage('about');
        $redirect = new PageRedirect();
        $redirect->setAttributes([
            'from_path' => 'old-about',
            'to_path' => 'about',
            'code' => 301,
            'page_id' => $page->id,
            'created_at' => time(),
        ], false);
        self::assertTrue($redirect->save(false));
        Yii::$app->pages->invalidate();

        $response = $this->open('old-about/');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('/about/', $this->location($response));
    }

    public function testWithoutSuffixAddressesStayWithoutSlash(): void
    {
        Yii::$app->urlManager->suffix = null;
        $page = $this->createPage('about');

        self::assertSame('/about', Yii::$app->pages->url($page));
        $response = $this->open('about/');
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('/about', $this->location($response));
    }

    private function location(Response $response): string
    {
        return (string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
    }

    private function createPage(string $slug, string $title = 'Страница'): Page
    {
        $page = new Page();
        $page->setAttributes(['slug' => $slug, 'title' => $title, 'status' => 'published', 'body' => '<p>Текст</p>'], false);
        self::assertTrue($page->save(), print_r($page->getErrors(), true));
        Yii::$app->pages->invalidate();

        return $page;
    }

    /**
     * GET-запрос к сайту: разбор адреса правилами urlManager и запуск найденного действия.
     */
    private function open(string $pathInfo): mixed
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = Yii::$app->request;
        $request->setPathInfo($pathInfo);
        $request->setQueryParams([]);

        $result = Yii::$app->urlManager->parseRequest($request);
        self::assertNotFalse($result, "Адрес /{$pathInfo} не разобран");
        [$route, $params] = $result;

        return Yii::$app->runAction($route, $params);
    }
}
