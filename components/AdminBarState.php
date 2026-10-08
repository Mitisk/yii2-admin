<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\Menu;
use Mitisk\Yii2Admin\Module;
use Yii;
use yii\base\Component;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * Состояние панели администратора: всё, что нужно JS для отрисовки.
 *
 * Расширение из проекта — class-level событие:
 * ```php
 * \yii\base\Event::on(AdminBarState::class, AdminBarState::EVENT_BUILD, function (AdminBarBuildEvent $e) {
 *     $e->state->panels[] = ['id' => 'seo', 'label' => 'SEO', 'icon' => 'search', 'items' => [...]];
 *     $e->state->badges['orders'] = 3;
 * });
 * ```
 *
 * @category Component
 * @package  Mitisk\Yii2Admin\components
 * @author   Mitisk <akimkinpit@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/mitisk/yii2-admin
 */
class AdminBarState extends Component
{
    public const EVENT_BUILD = 'buildAdminBar';

    public string $version = Module::VERSION;

    /** @var array<string, mixed> */
    public array $user = [];

    /** @var array<string, string> */
    public array $urls = [];

    /** @var array{param: string, token: string} */
    public array $csrf = ['param' => '', 'token' => ''];

    /** @var array<string, string> */
    public array $endpoints = [];

    /** @var array<string, mixed> */
    public array $context = [];

    /** @var array<int, array<string, mixed>> */
    public array $menu = [];

    /** @var array<int, array<string, mixed>> */
    public array $actions = [];

    /** @var array<int, array<string, mixed>> */
    public array $panels = [];

    /** @var array<string, mixed> */
    public array $badges = [];

    /** @var array<string, mixed> */
    public array $impersonation = ['active' => false];

    /** @var array<string, bool> */
    public array $features = ['inlineEdit' => true];

    /** @var array<string, mixed> */
    public array $prefs = [];

    /** @var array<string, string> URL ассетов панели (css). */
    public array $assets = [];

    /**
     * Режимы просмотра: включены ли «гость» и «черновики», имена их cookie.
     *
     * @var array{guest?: bool, drafts?: bool, cookies?: array<string, string>}
     */
    public array $view = [];

    /**
     * Собирает состояние для текущего администратора.
     *
     * @param AdminBarComponent $bar     Компонент с контекстом страницы.
     * @param string|null       $url     URL страницы (в client-режиме приходит от загрузчика).
     * @param array|null        $ctxHint Подсказка контекста из загрузчика: ['class' => ..., 'id' => ...].
     */
    public static function build(AdminBarComponent $bar, ?string $url = null, ?array $ctxHint = null): self
    {
        $state = new self();
        $request = Yii::$app->request;
        $adminUser = $bar->getAdminUser();
        /** @var AdminUser|null $identity */
        $identity = $adminUser?->getIdentity();

        $base = rtrim((string)$request->baseUrl, '/');

        // Пользователь
        if ($identity) {
            $roles = [];
            if (Yii::$app->authManager) {
                $roles = array_values(array_diff(
                    array_keys(Yii::$app->authManager->getRolesByUser($identity->id)),
                    ['guest', 'user']
                ));
            }
            $state->user = [
                'id' => (int)$identity->id,
                'name' => (string)($identity->name ?: $identity->username),
                'avatar' => $identity->image ?: null,
                'roles' => $roles,
            ];
        }

        // Адреса строит urlManager сайта: жёстко заданный слеш на конце при нормализаторе
        // слешей давал 301, и POST (выход, действия, правка) превращался в GET
        $state->urls = [
            'dashboard' => Url::to(['/admin/default/index']),
            'profile' => $identity ? Url::to(['/admin/user/update', 'id' => $identity->id]) : Url::to(['/admin/default/index']),
            'settings' => Url::to(['/admin/settings/index']),
            'components' => Url::to(['/admin/components/index']),
            'update' => Url::to(['/admin/default/update']),
            'logout' => Url::to(['/admin/default/logout']),
            'blocks' => Url::to(['/admin/content-block/index']),
            // key дописывает JS — параметр должен идти последним
            'blockEdit' => Url::to(['/admin/content-block/update', 'modal' => 1]) . '&key=',
        ];

        $state->csrf = [
            'param' => (string)$request->csrfParam,
            'token' => (string)$request->getCsrfToken(),
        ];

        $state->endpoints = [
            'state' => Url::to(['/admin/bar/state']),
            'action' => Url::to(['/admin/bar/action']),
            'attribute' => Url::to(['/admin/bar/attribute']),
            'block' => Url::to(['/admin/bar/block']),
        ];

        $state->prefs = [
            'position' => (string)Yii::$app->settings->get('ADMIN', 'bar_position', 'bottom') ?: 'bottom',
            'theme' => 'dark',
            'hotkey' => 'Alt+Shift+A',
        ];

        // Режимы просмотра
        $guestView = $bar->isGuestView();
        $state->view = [
            'guest' => $guestView,
            'drafts' => $bar->isDraftsOn(),
            'cookies' => [
                'guest' => AdminBarComponent::COOKIE_GUEST,
                'drafts' => AdminBarComponent::COOKIE_DRAFTS,
            ],
        ];

        // «Смотреть как гость»: панель свёрнута в кнопку выхода из режима,
        // меню, действия, панели и контекст не нужны
        if ($guestView) {
            $state->features = ['inlineEdit' => false, 'drafts' => false];
            return $state;
        }

        // Контекст
        $pageUrl = $url ?? (string)$request->url;
        $state->context = [
            'url' => $pageUrl,
            'route' => (string)(Yii::$app->requestedRoute ?? ''),
            'model' => null,
        ] + $bar->getContext();

        $model = $bar->getModel();
        if ($model === null && $ctxHint && !empty($ctxHint['class']) && !empty($ctxHint['id'])) {
            $model = self::resolveModel((string)$ctxHint['class'], $ctxHint['id']);
        }
        if ($model !== null) {
            $state->context['model'] = self::describeModel($bar, $model, $base);
        }

        // Меню админки, отфильтрованное по правам администратора
        $state->menu = self::buildMenu($bar, $base);

        // Действия и панели
        $state->actions = array_merge($bar->getServerActions(), $bar->getActions());
        $state->panels = $bar->getPanels();

        // SEO-панель: правило SeoManager для этой страницы (если проект не задал свою)
        if (!in_array('seo', array_column($state->panels, 'id'), true)) {
            $seo = self::buildSeoPanel($bar, $pageUrl, $base, $url === null);
            if ($seo !== null) {
                $state->panels[] = $seo;
            }
        }

        // Бейджи
        if ($bar->can('superAdminRole')) {
            try {
                $latest = Module::getLatestRelease();
                if ($latest && version_compare($latest, Module::VERSION, '>')) {
                    $state->badges['update'] = $latest;
                }
            } catch (\Throwable $e) {
                // сеть недоступна — без бейджа
            }
        }

        // Имперсонация
        if (Yii::$app->has('session') && Yii::$app->session->has('impersonator_id')) {
            $state->impersonation = [
                'active' => true,
                'returnUrl' => Url::to(['/admin/user/stop-impersonate']),
            ];
        }

        $state->features = [
            'inlineEdit' => true,
            'drafts' => $bar->isDraftsToggleAvailable(),
            'blocks' => $bar->can('editContent'),
        ];

        // Точка расширения
        $state->trigger(self::EVENT_BUILD, new AdminBarBuildEvent(['state' => $state]));

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'user' => $this->user,
            'urls' => $this->urls,
            'csrf' => $this->csrf,
            'endpoints' => $this->endpoints,
            'context' => $this->context,
            'menu' => $this->menu,
            'actions' => array_values($this->actions),
            'panels' => array_values($this->panels),
            'badges' => $this->badges,
            'impersonation' => $this->impersonation,
            'features' => $this->features,
            'prefs' => $this->prefs,
            'view' => $this->view,
            'assets' => $this->assets,
        ];
    }

    /**
     * SEO-панель на данных {@see SeoManager}: какое правило сработало для страницы
     * и что оно выводит; если правила нет — ссылка на создание правила под этот URL.
     *
     * Видна ролям `admin` и `superAdminRole` — тем же, кому открыт раздел SEO-правил.
     *
     * @param AdminBarComponent $bar        Компонент панели (проверка прав).
     * @param string            $url        URL страницы с query string.
     * @param string            $base       Базовый URL приложения.
     * @param bool              $serverMode Состояние собирается при рендере самой страницы:
     *                                      доступен фактический заголовок из View.
     *
     * @return array<string, mixed>|null Панель для `state.panels` или null.
     */
    private static function buildSeoPanel(AdminBarComponent $bar, string $url, string $base, bool $serverMode): ?array
    {
        if (!$bar->can('admin') && !$bar->can('superAdminRole')) {
            return null;
        }

        $seo = self::seoManager();
        try {
            $rule = $seo->findRule($url);
        } catch (\Throwable $e) {
            // Нет таблицы seo_rules (миграции не применены) — без панели
            Yii::warning('AdminBar SEO: ' . $e->getMessage(), __METHOD__);
            return null;
        }

        $admin = $base . '/admin/seo-rule/';
        $items = [];

        if ($serverMode) {
            $pageTitle = trim((string)Yii::$app->view->title);
            $items[] = $pageTitle !== ''
                ? ['label' => 'Заголовок страницы: ' . $pageTitle, 'value' => mb_strlen($pageTitle), 'icon' => 'eye']
                : ['label' => 'У страницы нет заголовка', 'icon' => 'alert'];
        }

        if ($rule === null) {
            $path = (string)(parse_url($url, PHP_URL_PATH) ?: '/');
            $items[] = ['label' => 'SEO-правило для страницы не найдено', 'icon' => 'alert'];
            $items[] = [
                'label' => 'Создать правило для этой страницы',
                'icon' => 'plus',
                'url' => $admin . 'create/?' . http_build_query(['pattern' => '^' . preg_quote($path, '#') . '$']),
            ];
        } else {
            // В client-режиме контекст подстановок страницы недоступен — видны шаблоны как есть
            $fields = [
                'title' => 'Title',
                'description' => 'Description',
                'keywords' => 'Keywords',
                'robots' => 'Robots',
                'og_title' => 'OG Title',
                'og_image' => 'OG Image',
            ];
            foreach ($fields as $attr => $label) {
                $value = trim((string)$seo->parse(isset($rule[$attr]) ? (string)$rule[$attr] : null));
                if ($value !== '') {
                    $items[] = ['label' => $label . ': ' . $value, 'value' => mb_strlen($value), 'icon' => 'file'];
                } elseif ($attr === 'title' || $attr === 'description') {
                    $items[] = ['label' => $label . ' не задан', 'icon' => 'alert'];
                }
            }
            $items[] = [
                'label' => 'Правило: ' . $rule['pattern'],
                'value' => '#' . $rule['id'],
                'icon' => 'edit',
                'url' => $admin . 'update/?id=' . (int)$rule['id'],
            ];
        }

        return [
            'id' => 'seo',
            'label' => 'SEO',
            'icon' => 'search',
            'url' => $admin,
            'permission' => null,
            'items' => $items,
        ];
    }

    /**
     * Компонент `seo` приложения, если он настроен и является {@see SeoManager}
     * (тогда доступен контекст подстановок страницы), иначе новый экземпляр.
     */
    private static function seoManager(): SeoManager
    {
        try {
            if (Yii::$app->has('seo')) {
                $seo = Yii::$app->get('seo');
                if ($seo instanceof SeoManager) {
                    return $seo;
                }
            }
        } catch (\Throwable $e) {
            // Компонент описан в конфиге, но не создаётся (например, неверный класс)
            Yii::warning('AdminBar SEO: ' . $e->getMessage(), __METHOD__);
        }
        return new SeoManager();
    }

    // ------------------------------------------------------------------

    /**
     * Описание контекстной модели для панели.
     *
     * @return array<string, mixed>|null
     */
    private static function describeModel(AdminBarComponent $bar, ActiveRecord $model, string $base): ?array
    {
        $class = get_class($model);
        $component = $bar->findComponent($class);
        if ($component === null || !$component->alias) {
            return null;
        }
        if (!$bar->can($class . '\view') && !$bar->can('admin')) {
            return null;
        }

        $pk = $model->getPrimaryKey();
        $id = is_array($pk) ? implode('-', $pk) : $pk;

        $label = null;
        if ($component->admin_label && $model->hasAttribute($component->admin_label)) {
            $label = $model->getAttribute($component->admin_label);
        }
        if ($label === null || $label === '') {
            foreach (['name', 'title', 'label'] as $attr) {
                if ($model->hasAttribute($attr) && $model->getAttribute($attr)) {
                    $label = $model->getAttribute($attr);
                    break;
                }
            }
        }

        $prefix = $base . '/admin/' . $component->alias . '/';
        $urls = ['index' => $prefix];
        if ($bar->canUpdate($class) && $component->can_update) {
            $urls['update'] = $prefix . 'update/?id=' . $id;
        }
        if ($component->can_view) {
            $urls['view'] = $prefix . 'view/?id=' . $id;
        }
        if ($component->can_create && ($bar->can($class . '\create') || $bar->can('admin'))) {
            $urls['create'] = $prefix . 'create/';
        }

        return [
            'class' => $class,
            'id' => $id,
            'label' => (string)($label ?? ('#' . $id)),
            'canUpdate' => $bar->canUpdate($class),
            'component' => [
                'alias' => $component->alias,
                'name' => $component->name,
            ],
            'urls' => $urls,
        ];
    }

    private static function resolveModel(string $class, mixed $id): ?ActiveRecord
    {
        if (!class_exists($class) || !is_subclass_of($class, ActiveRecord::class)) {
            return null;
        }
        try {
            $model = $class::findOne($id);
            return $model instanceof ActiveRecord ? $model : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Меню админки, отфильтрованное по правам администратора (а не фронтового user).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function buildMenu(AdminBarComponent $bar, string $base): array
    {
        $menu = Menu::find()->where(['alias' => 'admin'])->one();
        if ($menu === null) {
            return [];
        }
        $items = json_decode((string)$menu->data, true);
        if (!is_array($items)) {
            return [];
        }
        return self::filterMenu($bar, $items, $base);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private static function filterMenu(AdminBarComponent $bar, array $items, string $base): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rule = $item['rule'] ?? null;
            if ($rule && !$bar->can((string)$rule)) {
                continue;
            }
            $href = $item['href'] ?? '#';
            if (is_array($href)) {
                $href = Url::to($href);
            }
            $href = (string)$href;
            if ($href !== '#' && str_starts_with($href, '/') && $base !== '' && !str_starts_with($href, $base . '/')) {
                $href = $base . $href;
            }
            $children = !empty($item['children']) && is_array($item['children'])
                ? self::filterMenu($bar, $item['children'], $base)
                : [];
            $out[] = [
                'text' => (string)($item['text'] ?? ''),
                'href' => $href,
                'icon' => (string)($item['icon'] ?? ''),
                'target' => (string)($item['target'] ?? '_self'),
                'children' => $children,
            ];
        }
        return $out;
    }
}
