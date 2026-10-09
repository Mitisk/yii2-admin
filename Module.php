<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin;

use Yii;
use yii\base\BootstrapInterface;
use Mitisk\Yii2Admin\models\AdminControllerMap;
use Mitisk\Yii2Admin\components\ExtAdminController;

final class Module extends \yii\base\Module implements BootstrapInterface
{
    public const VERSION = '1.7.0';

    public $controllerNamespace = 'Mitisk\Yii2Admin\controllers';
    public $checkAccessPermissionAdministrateRbac = true;

    // Базовая статическая карта НЕ теряется
    public $controllerMap = [
        'core' => 'Mitisk\Yii2Admin\core\controllers\AdminController',
    ];

    public $layout = '@Mitisk/Yii2Admin/views/layouts/main.php';

    public function init()
    {
        parent::init();

        // Регистрируем компонент как 'adminUser', чтобы не конфликтовать с основным 'user'
        \Yii::$app->set('adminUser', [
            'class' => 'yii\web\User',
            'identityClass' => 'Mitisk\Yii2Admin\models\AdminUser',
            'enableAutoLogin' => true,
            'idParam' => '__admin_id',
            'identityCookie' => ['name' => '_admin_identity', 'httpOnly' => true],
            'loginUrl' => ['/admin/default/login'],
        ]);

        // Панель администратора на сайте
        if (!\Yii::$app->has('adminBar')) {
            \Yii::$app->set('adminBar', ['class' => 'Mitisk\\Yii2Admin\\components\\AdminBarComponent']);
        }

        // Текстовые блоки раздела «Контент»
        if (!\Yii::$app->has('blocks')) {
            \Yii::$app->set('blocks', ['class' => 'Mitisk\\Yii2Admin\\components\\ContentBlockService']);
        }

        // Страницы раздела «Контент»
        if (!\Yii::$app->has('pages')) {
            \Yii::$app->set('pages', ['class' => 'Mitisk\\Yii2Admin\\components\\PageService']);
        }

        // Настройка authManager (RBAC)
        if (!\Yii::$app->has('authManager')) {
            \Yii::$app->set('authManager', [
                'class' => 'yii\rbac\DbManager',
                'defaultRoles' => ['guest', 'user'],
                'cache' => 'cache',
                'cacheKey' => 'rbac',
            ]);
        }

        // Добавляем парсер JSON
        $request = \Yii::$app->request;
        if ($request instanceof \yii\web\Request) {
            $parsers = $request->parsers;
            if (!isset($parsers['application/json'])) {
                $parsers['application/json'] = 'yii\web\JsonParser';
                $request->parsers = $parsers;
            }
        }

        \Yii::setAlias('@Mitisk/Yii2Admin', __DIR__);
    }

    // Все динамические вещи и правила — здесь
    public function bootstrap($app)
    {
        // Консольное приложение: только команды модуля (php yii admin/update)
        if ($app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'Mitisk\\Yii2Admin\\commands';
            return;
        }

        // 1) Правила URL
        $app->getUrlManager()->addRules([
            // Явные маршруты логина/логаута
            'admin/login'  => 'admin/default/login',
            'admin/logout' => 'admin/default/logout',
            'admin' => 'admin/default/index',

            [
                'class' => \Mitisk\Yii2Admin\components\UrlRule::class,
                'pattern' => 'admin/<controller:\w+>/<action:\w+>', // Шаблон URL
                'route' => 'admin/<controller>/<action>',          // Маршрут для обработки
            ],

            // Общие шаблоны админки
            'admin/<controller:\w+>' => 'admin/<controller>/index',
            'admin/<controller:\w+>/<action:\w+>' => 'admin/<controller>/<action>',
        ], false);

        // 1a) Страницы раздела «Контент»: фронтовый контроллер, правило первым (совпадает только
        // с известными адресами) и sitemap.xml, если у сайта нет своих
        $this->bootstrapPages($app);

        // 2) Динамическая карта контроллеров из БД
        try {
            $dbMap = $this->buildControllerMapFromDb();
            // ВАЖНО: слить, а не перезаписать, чтобы не потерять 'core'
            $this->controllerMap = array_merge($this->controllerMap, $dbMap);
        } catch (\Throwable $e) {
            Yii::warning('admin controllerMap load failed: ' . $e->getMessage(), __METHOD__);
        }
    }

    public function beforeAction($action)
    {
        if (Yii::$app->request->isConsoleRequest) {
            return parent::beforeAction($action);
        }

        if (Yii::$app->has('adminUser')) {
            Yii::$app->set('user', Yii::$app->get('adminUser'));
        }
        Yii::$app->errorHandler->errorAction = 'admin/default/error';

        if (Yii::$app->request->isConsoleRequest === false) {
            $route = $action->uniqueId;
            $skipRoutes = [
                'admin/default/login',
                'admin/default/check-user',
                'admin/default/upgrade',
                'admin/default/run-migrations',
                // Самообновление: между composer update и финальным шагом
                // версия в коде уже новая, а в БД ещё старая — не редиректим
                'admin/default/update',
                'admin/default/update-start',
                'admin/default/update-status',
                // Панель на сайте: сама отвечает 401 вместо редиректа на логин
                'admin/bar/state',
                'admin/bar/action',
                'admin/bar/attribute',
                'admin/bar/block',
            ];

            if (Yii::$app->user->isGuest && !in_array($route, $skipRoutes, true)) {
                Yii::$app->response->redirect(['/admin/default/login'])->send();
                return false;
            }

            // Проверка версии — редирект на страницу обновления
            if (!Yii::$app->user->isGuest && !in_array($route, $skipRoutes, true)) {
                if ($this->needsUpgrade()) {
                    Yii::$app->response->redirect(['/admin/default/upgrade'])->send();
                    return false;
                }
            }
        }
        return parent::beforeAction($action);
    }

    /**
     * Проверяет, отличается ли текущая версия модуля от сохранённой.
     */
    public function needsUpgrade(): bool
    {
        try {
            $saved = Yii::$app->settings->get('GENERAL', 'version');
            return $saved !== self::VERSION;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private const GITHUB_REPO = 'Mitisk/yii2-admin';
    private const GITHUB_CACHE_KEY = 'admin_latest_release';
    private const GITHUB_CACHE_TTL = 3600;

    /**
     * Возвращает последнюю версию с GitHub или null.
     * Кеширует результат на 1 час.
     */
    public static function getLatestRelease(): ?string
    {
        $cache = Yii::$app->cache;
        $cached = $cache->get(self::GITHUB_CACHE_KEY);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $version = null;
        try {
            $url = 'https://api.github.com/repos/'
                . self::GITHUB_REPO
                . '/releases/latest';
            $ctx = stream_context_create([
                'http' => [
                    'header' => "User-Agent: Yii2Admin\r\n"
                        . "Accept: application/vnd.github.v3+json\r\n",
                    'timeout' => 5,
                ],
            ]);
            $json = @file_get_contents($url, false, $ctx);
            if ($json) {
                $data = json_decode($json, true);
                $tag = $data['tag_name'] ?? '';
                $version = ltrim($tag, 'vV');
            }
        } catch (\Throwable $e) {
            // Не блокируем работу при ошибке сети
        }

        $cache->set(
            self::GITHUB_CACHE_KEY,
            $version ?: '',
            self::GITHUB_CACHE_TTL
        );

        return $version ?: null;
    }

    /**
     * Страницы на сайте: контроллер, правило URL и sitemap.xml.
     *
     * Свой `controllerMap['page']` сайта модуль не трогает (так подменяют контроллер страниц).
     * Если у сайта есть обычный `app\controllers\PageController`, страницы живут под id
     * `content-page`: в адресах он не виден, а маршруты сайта `page/*` остаются его.
     * `sitemap.xml` модуль отдаёт, только если у сайта нет своего правила для этого адреса.
     *
     * @param \yii\web\Application $app
     */
    private function bootstrapPages($app): void
    {
        $pages = $app->get('pages');
        $controllerId = $pages instanceof \Mitisk\Yii2Admin\components\PageService ? $pages->controllerId : 'page';
        if (!isset($app->controllerMap[$controllerId])) {
            $siteController = rtrim($app->controllerNamespace, '\\') . '\\' . \yii\helpers\Inflector::id2camel($controllerId) . 'Controller';
            if ($pages instanceof \Mitisk\Yii2Admin\components\PageService && class_exists($siteController)) {
                $controllerId = $pages->controllerId = 'content-page';
            }
            $app->controllerMap[$controllerId] = \Mitisk\Yii2Admin\controllers\front\PageController::class;
        }

        $rules = [['class' => \Mitisk\Yii2Admin\components\PageUrlRule::class]];
        $hasSitemap = false;
        foreach ($app->getUrlManager()->rules as $rule) {
            if ($rule instanceof \yii\web\UrlRule && trim((string)$rule->name, '/') === 'sitemap.xml') {
                $hasSitemap = true;
                break;
            }
        }
        if (!$hasSitemap) {
            $rules['sitemap.xml'] = $controllerId . '/sitemap';
        }
        $app->getUrlManager()->addRules($rules, false);
    }

    private function buildControllerMapFromDb(): array
    {
        $map = [];
        $rows = AdminControllerMap::find()->where(['enabled' => 1])->all();

        foreach ($rows as $row) {
            $id = $row->controller_id;
            $class = $row->class;
            $cfg = is_array($row->config) ? $row->config : (json_decode((string)$row->config, true) ?: []);

            if (!class_exists($class)) {
                Yii::warning("Controller class not found: {$class}");
                continue;
            }
            if (!is_subclass_of($class, ExtAdminController::class)) {
                Yii::warning("Controller {$class} must extend ExtAdminController");
                continue;
            }
            $map[$id] = array_merge(['class' => $class], $cfg);
        }
        return $map;
    }
}
