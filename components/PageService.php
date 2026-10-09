<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\components\content\ShortcodeService;
use Mitisk\Yii2Admin\components\pages\PageHtmlPurifier;
use Mitisk\Yii2Admin\components\pages\PagePath;
use Mitisk\Yii2Admin\components\pages\PageSitemapEvent;
use Mitisk\Yii2Admin\components\pages\PageTree;
use Mitisk\Yii2Admin\components\pages\PreviewToken;
use Mitisk\Yii2Admin\components\pages\TemplateFinder;
use Mitisk\Yii2Admin\enums\PageStatus;
use Mitisk\Yii2Admin\models\Page;
use Mitisk\Yii2Admin\models\PageRedirect;
use Yii;
use yii\base\Component;
use yii\caching\TagDependency;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Страницы на сайте (`Yii::$app->pages`).
 *
 * Карта адресов и редиректов читается одним запросом и кэшируется до любого
 * изменения страниц (TagDependency `page`). Отложенная публикация проверяется
 * по времени запроса, поэтому кэш для неё сбрасывать не нужно.
 */
class PageService extends Component
{
    public const EVENT_SITEMAP = 'sitemap';

    /** Папка шаблонов сайта. */
    public string $templateDir = '@app/views/page';

    /** Шаблон модуля по умолчанию. */
    public string $fallbackTemplate = '@Mitisk/Yii2Admin/views/front/page/default.php';

    /** Срок ссылки предпросмотра, секунд. */
    public int $previewTtl = 86400;

    /**
     * id фронтового контроллера страниц в `controllerMap` приложения. В адресах не виден.
     * Модуль меняет его на `content-page`, если у сайта есть свой `PageController`.
     */
    public string $controllerId = 'page';

    /** @var \Closure|null Источник карты адресов вместо БД (тесты). */
    public ?\Closure $mapLoader = null;

    /** @var \Closure|null Источник редиректов вместо БД (тесты). */
    public ?\Closure $redirectLoader = null;

    /** Страница, текст которой сейчас рендерится (для `[children]` и `[toc]`). */
    public ?Page $currentPage = null;

    /** @var array<string, array{id: int, status: string, published_at: ?int}>|null */
    private ?array $_map = null;

    /** @var array<string, array{to: string, code: int}>|null */
    private ?array $_redirects = null;

    /** @var array<int, Page|null> */
    private array $_pages = [];

    private ?ShortcodeService $_shortcodes = null;

    public function getEnabled(): bool
    {
        return (bool)Yii::$app->settings->get('ADMIN', 'pages_enabled', true);
    }

    // ------------------------------------------------------------------
    // Карта адресов
    // ------------------------------------------------------------------

    /** @return array<string, array{id: int, status: string, published_at: ?int}> */
    public function map(): array
    {
        if ($this->_map !== null) {
            return $this->_map;
        }
        return $this->_map = $this->safely(fn(): array => $this->mapLoader !== null
            ? ($this->mapLoader)()
            : $this->cached(['page', 'map'], static function (): array {
                $out = [];
                foreach (Page::find()->select(['id', 'path', 'status', 'published_at'])->asArray()->all() as $row) {
                    $out[$row['path']] = [
                        'id' => (int)$row['id'],
                        'status' => (string)$row['status'],
                        'published_at' => $row['published_at'] === null ? null : (int)$row['published_at'],
                    ];
                }
                return $out;
            }));
    }

    /** @return array<string, array{to: string, code: int}> */
    public function redirects(): array
    {
        if ($this->_redirects !== null) {
            return $this->_redirects;
        }
        return $this->_redirects = $this->safely(fn(): array => $this->redirectLoader !== null
            ? ($this->redirectLoader)()
            : $this->cached(['page', 'redirects'], static function (): array {
                $out = [];
                foreach (PageRedirect::find()->select(['from_path', 'to_path', 'code'])->asArray()->all() as $row) {
                    $out[$row['from_path']] = ['to' => (string)$row['to_path'], 'code' => (int)$row['code']];
                }
                return $out;
            }));
    }

    /**
     * Правило страниц стоит первым на каждом запросе. Код модуля обновили, а миграции ещё
     * не прошли (нет таблиц) — сайт и страница обновления админки должны открываться,
     * поэтому ошибка БД даёт пустую карту, а не 500. Пустой результат не кэшируется.
     *
     * @param \Closure(): array $load
     */
    private function safely(\Closure $load): array
    {
        try {
            return $load();
        } catch (\yii\db\Exception $e) {
            Yii::warning('Pages: ' . $e->getMessage() . ' (миграции модуля применены?)', __METHOD__);
            return [];
        }
    }

    /** @return array{id: int, status: string, published_at: ?int}|null */
    public function resolve(string $path): ?array
    {
        return $this->map()[trim($path, '/')] ?? null;
    }

    /** @param array{status: string, published_at: ?int} $row */
    public function isLive(array $row, ?int $now = null): bool
    {
        return $row['status'] === PageStatus::Published->value
            && ($row['published_at'] === null || $row['published_at'] <= ($now ?? time()));
    }

    /**
     * Черновик видит администратор с `viewContent` или владелец ссылки предпросмотра.
     * В режиме бара «Смотреть как гость» администратор видит сайт как гость — без черновиков.
     */
    public function canPreview(int $pageId, ?string $token): bool
    {
        $key = $this->previewKey();
        if ($token !== null && $key !== '' && PreviewToken::verify($pageId, $token, $key)) {
            return true;
        }
        if (!Yii::$app->has('adminBar')) {
            return false;
        }
        $bar = Yii::$app->get('adminBar');
        return $bar->can('viewContent') && !$bar->isGuestView();
    }

    public function pathById(int $id): ?string
    {
        foreach ($this->map() as $path => $row) {
            if ($row['id'] === $id) {
                return $path;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Страницы
    // ------------------------------------------------------------------

    public function find(string $path, bool $onlyLive = true): ?Page
    {
        $row = $this->resolve($path);
        if ($row === null || ($onlyLive && !$this->isLive($row))) {
            return null;
        }
        return $this->get($row['id']);
    }

    public function get(int $id): ?Page
    {
        if (!array_key_exists($id, $this->_pages)) {
            $this->_pages[$id] = Page::findOne($id);
        }
        return $this->_pages[$id];
    }

    /** Адрес страницы на сайте. */
    public function url(string|Page $pathOrPage, array $params = []): string
    {
        $path = $pathOrPage instanceof Page ? $pathOrPage->path : trim($pathOrPage, '/');
        return Url::to(['/' . $this->controllerId . '/view', 'path' => $path] + $params);
    }

    /**
     * Дерево страниц. Для сайта — только опубликованные, для админки — все.
     *
     * @return list<array{item: Page, children: list<array>, depth: int}>
     */
    public function tree(bool $onlyLive = true): array
    {
        $query = Page::find()->light();
        if ($onlyLive) {
            $query->published();
        }
        return PageTree::build($query->all());
    }

    /**
     * Ссылки для `yii\widgets\Breadcrumbs`. Неопубликованный родитель опубликованной страницы
     * в крошки гостя не попадает: ни его заголовка, ни ссылки на 404.
     *
     * @return list<array{label: string, url?: string}>
     */
    public function breadcrumbs(Page $page): array
    {
        $chain = [];
        $current = $page;
        $guard = 0;
        while ($current !== null && $guard++ < 50) {
            array_unshift($chain, $current);
            $current = $current->parent_id ? $this->get((int)$current->parent_id) : null;
        }
        $out = [];
        $last = count($chain) - 1;
        foreach ($chain as $i => $item) {
            if ($i === $last) {
                $out[] = ['label' => $item->title];
            } elseif ($item->isLive() || ($item->getStatus() !== PageStatus::Archived && $this->canPreview((int)$item->id, null))) {
                $out[] = ['label' => $item->title, 'url' => $this->url($item)];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Шаблоны, шорткоды, тело
    // ------------------------------------------------------------------

    /** @return array<string, string> */
    public function templates(): array
    {
        $found = TemplateFinder::find(Yii::getAlias($this->templateDir));
        return $found + [TemplateFinder::DEFAULT => 'Обычная страница'];
    }

    /**
     * Представление шаблона для `Controller::render()` — в виде алиаса, а не абсолютного пути:
     * абсолютный путь Windows Yii считает относительным и ищет его в папке контроллера.
     */
    public function templateView(string $template): string
    {
        $name = basename($template);
        $file = Yii::getAlias($this->templateDir) . '/' . $name . '.php';
        return is_file($file) ? rtrim($this->templateDir, '/') . '/' . $name : $this->fallbackTemplate;
    }

    public function shortcodes(): ShortcodeService
    {
        if ($this->_shortcodes === null) {
            $this->_shortcodes = (new ShortcodeService())
                ->register('block', static fn(array $a): string => Yii::$app->has('blocks') && isset($a['key'])
                    ? Yii::$app->get('blocks')->renderIfExists((string)$a['key'])
                    : '')
                ->register('page', function (array $a): string {
                    $page = isset($a['slug']) ? $this->find((string)$a['slug']) : null;
                    if ($page === null) {
                        return Html::encode((string)($a['text'] ?? ''));
                    }
                    return Html::a(Html::encode($a['text'] ?? $page->title), $this->url($page));
                })
                ->register('children', function (array $a): string {
                    $parent = isset($a['of']) ? $this->find((string)$a['of']) : $this->currentPage;
                    if ($parent === null) {
                        return '';
                    }
                    $items = Page::find()->light()->published()->children((int)$parent->id)->ordered()->all();
                    return $items === []
                        ? ''
                        : Html::ul($items, ['item' => fn(Page $p): string => Html::tag('li', Html::a(Html::encode($p->title), $this->url($p)))]);
                })
                ->register('toc', fn(): string => $this->toc((string)($this->currentPage?->body ?? '')));
        }
        return $this->_shortcodes;
    }

    public function renderBody(Page $page): string
    {
        $this->currentPage = $page;
        try {
            return $this->shortcodes()->render((string)$page->body);
        } finally {
            $this->currentPage = null;
        }
    }

    /** Оглавление из `<h2>`/`<h3>`; якоря `h-N` проставляет {@see anchorHeadings()}. */
    private function toc(string $body): string
    {
        preg_match_all('~<h([23])[^>]*>(.*?)</h\1>~isu', $body, $m, PREG_SET_ORDER);
        if ($m === []) {
            return '';
        }
        $items = [];
        foreach ($m as $i => $h) {
            // Сущности из редактора (&amp;, &nbsp;) — в символы, иначе Html::encode закодирует их второй раз
            $text = trim(html_entity_decode(strip_tags($h[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $items[] = Html::tag('li', Html::a(Html::encode($text), '#h-' . ($i + 1)), ['class' => 'toc-level-' . $h[1]]);
        }
        return Html::tag('nav', Html::tag('ul', implode('', $items)), ['class' => 'page-toc']);
    }

    /** Проставляет `id="h-N"` заголовкам, чтобы работали якоря оглавления. */
    public function anchorHeadings(string $html): string
    {
        $i = 0;
        return (string)preg_replace_callback('~<h([23])(\s[^>]*)?>~i', static function (array $m) use (&$i): string {
            $i++;
            return str_contains((string)($m[2] ?? ''), ' id=') ? $m[0] : '<h' . $m[1] . ($m[2] ?? '') . ' id="h-' . $i . '">';
        }, $html);
    }

    // ------------------------------------------------------------------
    // Предпросмотр, sitemap, настройки
    // ------------------------------------------------------------------

    /** Ссылка с токеном предпросмотра; без ключа подписи — обычный адрес (черновик откроет только админ). */
    public function previewUrl(Page $page): string
    {
        $key = $this->previewKey();
        if ($key === '') {
            Yii::warning('Pages: нет request.cookieValidationKey — ссылки предпросмотра отключены', __METHOD__);
            return $this->url($page);
        }
        return $this->url($page, ['preview' => PreviewToken::create((int)$page->id, $key, $this->previewTtl)]);
    }

    /** @return list<array{loc: string, lastmod: int|null}> */
    public function sitemapEntries(): array
    {
        $entries = [];
        foreach (Page::find()->light()->published()->andWhere(['noindex' => 0])->orderBy(['path' => SORT_ASC])->all() as $page) {
            $entries[] = ['loc' => $this->url($page), 'lastmod' => (int)$page->updated_at];
        }
        $event = new PageSitemapEvent(['entries' => $entries]);
        $this->trigger(self::EVENT_SITEMAP, $event);
        return $event->entries;
    }

    /** @return list<string> */
    public function iframeHosts(): array
    {
        return PageHtmlPurifier::hostsFromSetting((string)Yii::$app->settings->get('ADMIN', 'pages_iframe_hosts', ''));
    }

    /**
     * Сегменты, которые корневая страница не может занять: модули, карта контроллеров,
     * файлы контроллеров сайта (в kebab-case) и первые сегменты правил urlManager
     * (`'login' => 'site/login'`, `<module:(partner|cabinet)>`) — правило страниц стоит
     * первым и перекрыло бы их.
     *
     * @return list<string>
     */
    public function reservedSegments(): array
    {
        $out = array_merge(array_keys(Yii::$app->getModules()), array_keys(Yii::$app->controllerMap));
        $dir = Yii::getAlias('@app/controllers');
        foreach (glob($dir . '/*Controller.php') ?: [] as $file) {
            $out[] = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '-$0', basename($file, 'Controller.php')));
        }
        foreach (Yii::$app->getUrlManager()->rules as $rule) {
            if ($rule instanceof \yii\web\UrlRule) {
                array_push($out, ...PagePath::ruleSegments((string)$rule->name));
            }
        }
        return array_values(array_unique(array_merge(PagePath::RESERVED, array_map('strval', $out))));
    }

    public function invalidate(): void
    {
        $this->_map = null;
        $this->_redirects = null;
        $this->_pages = [];
        Page::invalidateCache();
    }

    /**
     * Ключ подписи ссылок предпросмотра. Пустой — ссылки отключены: подставлять что-то
     * публичное (id приложения) нельзя, иначе токен подделает кто угодно.
     */
    private function previewKey(): string
    {
        $request = Yii::$app->getRequest();
        return $request instanceof \yii\web\Request ? (string)$request->cookieValidationKey : '';
    }

    private function cached(array $key, \Closure $loader): array
    {
        if (!Yii::$app->has('cache')) {
            return $loader();
        }
        return Yii::$app->cache->getOrSet($key, $loader, 0, new TagDependency(['tags' => Page::CACHE_TAG]));
    }
}
