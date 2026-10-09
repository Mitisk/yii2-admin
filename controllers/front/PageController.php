<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers\front;

use Mitisk\Yii2Admin\components\PageService;
use Mitisk\Yii2Admin\components\SeoManager;
use Mitisk\Yii2Admin\models\Page;
use Mitisk\Yii2Admin\models\PageRedirect;
use Yii;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Страницы раздела «Контент» на сайте. Регистрируется модулем как `controllerMap['page']`,
 * сайт может подменить своим классом. Лейаут — лейаут сайта по умолчанию.
 */
class PageController extends Controller
{
    /** Страница по id из {@see \Mitisk\Yii2Admin\components\PageUrlRule}. */
    public function actionView(int $id): string|Response
    {
        $pages = $this->pages();
        $page = $pages->get($id);
        if ($page === null || $page->getStatus()->value === 'archived') {
            throw new NotFoundHttpException('Страница не найдена.');
        }
        $preview = Yii::$app->request->get('preview');
        $preview = is_string($preview) ? $preview : null;
        $live = $page->isLive();
        if (!$live && !$pages->canPreview((int)$page->id, $preview)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }
        // Каноничный адрес: пришли с другим регистром или слешем на конце — 301
        // (правило страниц не проходит через UrlNormalizer, поэтому сравниваем путь как есть)
        $requested = (string)Yii::$app->request->getPathInfo();
        if ($requested !== $page->path) {
            return $this->redirect($pages->url($page, $preview !== null ? ['preview' => $preview] : []), 301);
        }
        if (!$live) {
            Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        }

        $this->registerSeo($page);
        if (Yii::$app->has('adminBar')) {
            Yii::$app->get('adminBar')->setModel($page);
        }

        $content = $pages->anchorHeadings($pages->renderBody($page));
        return $this->render($pages->templateView($page->template), ['page' => $page, 'content' => $content]);
    }

    /** Старый адрес страницы → новый. */
    public function actionRedirect(string $to, int $code = 301): Response
    {
        $from = trim((string)Yii::$app->request->getPathInfo(), '/');
        PageRedirect::updateAll(['hits' => new \yii\db\Expression('[[hits]] + 1'), 'last_hit_at' => time()], ['from_path' => $from]);
        return $this->redirect('/' . ltrim($to, '/'), $code === 302 ? 302 : 301);
    }

    public function actionSitemap(): Response
    {
        if (!$this->pages()->getEnabled()) {
            throw new NotFoundHttpException('Страница не найдена.');
        }
        $xml ='<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($this->pages()->sitemapEntries() as $entry) {
            $xml .= '  <url><loc>' . Html::encode(Url::to($entry['loc'], true)) . '</loc>'
                . (!empty($entry['lastmod']) ? '<lastmod>' . date('Y-m-d', (int)$entry['lastmod']) . '</lastmod>' : '')
                . "</url>\n";
        }
        $xml .= '</urlset>';
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->content = $xml;
        return $response;
    }

    /**
     * Теги страницы главнее правил SeoManager: сначала правила, потом поля страницы
     * под теми же ключами перекрывают их.
     */
    private function registerSeo(Page $page): void
    {
        $view = $this->getView();
        if (Yii::$app->has('seo')) {
            try {
                $seo = Yii::$app->get('seo');
                if ($seo instanceof SeoManager) {
                    $seo->register();
                }
            } catch (\Throwable $e) {
                Yii::warning('Page SEO: ' . $e->getMessage(), __METHOD__);
            }
        }
        $title = $page->seo_title ?: $page->title;
        $plain = trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)$page->body)));
        $description = $page->seo_description ?: ($page->excerpt ?: StringHelper::truncate($plain, 160));
        $view->title = $title;
        $view->registerMetaTag(['name' => 'description', 'content' => $description], 'description');
        if ($page->seo_keywords) {
            $view->registerMetaTag(['name' => 'keywords', 'content' => $page->seo_keywords], 'keywords');
        }
        if ($page->noindex) {
            $view->registerMetaTag(['name' => 'robots', 'content' => 'noindex, nofollow'], 'robots');
        }
        $canonical = $page->canonical ?: Url::to($this->pages()->url($page), true);
        $view->registerLinkTag(['rel' => 'canonical', 'href' => $canonical], 'canonical');
        $view->registerMetaTag(['property' => 'og:title', 'content' => $title], 'og:title');
        $view->registerMetaTag(['property' => 'og:description', 'content' => $description], 'og:description');
        $view->registerMetaTag(['property' => 'og:url', 'content' => $canonical], 'og:url');
        $view->registerMetaTag(['property' => 'og:type', 'content' => 'article'], 'og:type');
        if ($image = $page->getOgImageUrl()) {
            $view->registerMetaTag(['property' => 'og:image', 'content' => Url::to($image, true)], 'og:image');
        }
    }

    private function pages(): PageService
    {
        /** @var PageService $pages */
        $pages = Yii::$app->get('pages');
        return $pages;
    }
}
