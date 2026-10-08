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

        $state->urls = [
            'dashboard' => $base . '/admin/',
            'profile' => $identity ? $base . '/admin/user/update/?id=' . $identity->id : $base . '/admin/',
            'settings' => $base . '/admin/settings/',
            'components' => $base . '/admin/components/',
            'update' => $base . '/admin/default/update/',
            'logout' => $base . '/admin/default/logout/',
        ];

        $state->csrf = [
            'param' => (string)$request->csrfParam,
            'token' => (string)$request->getCsrfToken(),
        ];

        $state->endpoints = [
            'state' => $base . '/admin/bar/state/',
            'action' => $base . '/admin/bar/action/',
            'attribute' => $base . '/admin/bar/attribute/',
        ];

        // Контекст
        $state->context = [
            'url' => $url ?? (string)$request->url,
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
                'returnUrl' => $base . '/admin/user/stop-impersonate/',
            ];
        }

        $state->features = [
            'inlineEdit' => true,
        ];

        $state->prefs = [
            'position' => (string)Yii::$app->settings->get('ADMIN', 'bar_position', 'bottom') ?: 'bottom',
            'theme' => 'dark',
            'hotkey' => 'Alt+Shift+A',
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
            'assets' => $this->assets,
        ];
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
