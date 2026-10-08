<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminModel;
use Yii;
use yii\base\Component;
use yii\db\ActiveRecord;
use yii\web\User;

/**
 * Компонент панели администратора на сайте (`Yii::$app->adminBar`).
 *
 * Регистрируется модулем автоматически. Из контроллеров сайта в него передают
 * контекст текущей страницы: модель записи, дополнительные действия и панели.
 *
 * ```php
 * Yii::$app->adminBar->setModel($product);
 * Yii::$app->adminBar->addAction('export', 'Экспорт', ['url' => '/admin/reports/export/', 'icon' => 'download']);
 * Yii::$app->adminBar->addPanel('stats', 'Статистика', [['label' => 'Просмотров', 'value' => 120]]);
 * ```
 *
 * Серверные действия (выполняются по `POST /admin/bar/action/`) задаются в конфиге:
 * ```php
 * 'adminBar' => [
 *     'class' => \Mitisk\Yii2Admin\components\AdminBarComponent::class,
 *     'serverActions' => [
 *         'warm-cache' => [
 *             'label' => 'Прогреть кэш', 'icon' => 'zap', 'permission' => 'manageSystem',
 *             'confirm' => 'Запустить прогрев?', 'handler' => fn() => 'Кэш прогрет',
 *         ],
 *     ],
 * ],
 * ```
 *
 * @category Component
 * @package  Mitisk\Yii2Admin\components
 * @author   Mitisk <akimkinpit@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/mitisk/yii2-admin
 */
class AdminBarComponent extends Component
{
    /** Cookie режима «Смотреть как гость» (ставит JS панели). */
    public const COOKIE_GUEST = 'ab_guest';

    /** Cookie режима «Показывать черновики» (ставит JS панели). */
    public const COOKIE_DRAFTS = 'ab_drafts';

    /**
     * Серверные действия: id => [label, icon, permission, confirm, handler].
     * handler — callable, возвращает строку сообщения (или bool).
     *
     * @var array<string, array<string, mixed>>
     */
    public array $serverActions = [];

    /**
     * Показывать тумблер «Черновики» на всех страницах.
     *
     * По умолчанию тумблер появляется только на страницах, которые сами вызвали
     * {@see showDrafts()}. В client-режиме состояние собирается отдельным запросом,
     * и узнать это нельзя, поэтому там тумблер включают этим флагом.
     */
    public bool $draftsToggle = false;

    /** Страница спрашивала {@see showDrafts()} в этом запросе. */
    private bool $_draftsRequested = false;

    /** @var array<int, array<string, mixed>> Действия-ссылки, добавленные на странице. */
    private array $_actions = [];

    /** @var array<int, array<string, mixed>> Панели, добавленные на странице. */
    private array $_panels = [];

    /** @var array<string, mixed> Произвольный контекст страницы. */
    private array $_context = [];

    private ?ActiveRecord $_model = null;

    private ?bool $_isAdmin = null;

    public function init(): void
    {
        parent::init();

        if (!isset($this->serverActions['clear-cache'])) {
            $this->serverActions = ['clear-cache' => [
                'label' => 'Очистить кэш',
                'icon' => 'refresh',
                'permission' => 'manageSystem',
                'confirm' => 'Очистить кэш сайта?',
                'handler' => static function (): string {
                    Yii::$app->cache->flush();
                    return 'Кэш очищен';
                },
            ]] + $this->serverActions;
        }
    }

    // ------------------------------------------------------------------
    // Пользователь
    // ------------------------------------------------------------------

    /**
     * Компонент пользователя админки (не фронтовый `user`).
     */
    public function getAdminUser(): ?User
    {
        if (!Yii::$app->has('adminUser')) {
            return null;
        }
        $user = Yii::$app->get('adminUser');
        return $user instanceof User ? $user : null;
    }

    /**
     * Залогинен ли администратор с правом доступа в админку.
     */
    public function isAdmin(): bool
    {
        if ($this->_isAdmin !== null) {
            return $this->_isAdmin;
        }
        $this->_isAdmin = false;
        try {
            $user = $this->getAdminUser();
            if ($user && !$user->getIsGuest()) {
                $this->_isAdmin = $user->can('accessAdmin') || $user->can('admin');
            }
        } catch (\Throwable $e) {
            Yii::warning('AdminBar: ' . $e->getMessage(), __METHOD__);
        }
        return $this->_isAdmin;
    }

    /**
     * Проверка права администратора (не фронтового пользователя).
     */
    public function can(string $permission): bool
    {
        $user = $this->getAdminUser();
        return $user !== null && !$user->getIsGuest() && $user->can($permission);
    }

    /**
     * Может ли администратор редактировать записи модели.
     */
    public function canUpdate(ActiveRecord|string $model): bool
    {
        $class = is_object($model) ? get_class($model) : $model;
        return $this->can($class . '\update') || $this->can('admin');
    }

    // ------------------------------------------------------------------
    // Режимы просмотра
    // ------------------------------------------------------------------

    /**
     * Администратор включил «Смотреть как гость»: панель сворачивается в кнопку
     * выхода из режима, inline-правка и черновики выключены.
     */
    public function isGuestView(): bool
    {
        return $this->readFlag(self::COOKIE_GUEST) && $this->isAdmin();
    }

    /**
     * Показывать ли черновики на текущей странице.
     *
     * Сайт вызывает метод в своих выборках, например в ActiveQuery:
     * ```php
     * public function published(): static
     * {
     *     return Yii::$app->adminBar->showDrafts() ? $this : $this->andWhere(['status' => Status::Published->value]);
     * }
     * ```
     * Для посетителя всегда false и без запросов к БД. Вызов метода сам по себе
     * показывает администратору тумблер «Черновики» на этой странице.
     */
    public function showDrafts(): bool
    {
        $this->_draftsRequested = true;
        return $this->isDraftsOn();
    }

    /**
     * Включён ли режим «Черновики», без отметки, что страница его спрашивала.
     */
    public function isDraftsOn(): bool
    {
        return $this->readFlag(self::COOKIE_DRAFTS) && $this->isAdmin() && !$this->isGuestView();
    }

    /**
     * Показывать ли администратору тумблер «Черновики».
     *
     * Включённый режим показываем всегда, чтобы его можно было выключить.
     */
    public function isDraftsToggleAvailable(): bool
    {
        return $this->draftsToggle || $this->_draftsRequested || $this->readFlag(self::COOKIE_DRAFTS);
    }

    /**
     * Флаг режима из cookie.
     *
     * Cookie ставит JS панели, поэтому она без подписи Yii и читается напрямую.
     * Это безопасно: значение — только переключатель, и действует он лишь вместе
     * с проверкой {@see isAdmin()}.
     *
     * @param string $name Имя cookie, одна из констант `COOKIE_*`.
     */
    private function readFlag(string $name): bool
    {
        return ($_COOKIE[$name] ?? null) === '1';
    }

    // ------------------------------------------------------------------
    // Контекст страницы
    // ------------------------------------------------------------------

    public function setModel(?ActiveRecord $model): static
    {
        $this->_model = $model;
        return $this;
    }

    public function getModel(): ?ActiveRecord
    {
        return $this->_model;
    }

    /**
     * Действие-ссылка в панели.
     *
     * @param array{icon?: string, url?: string, target?: string, confirm?: string, permission?: string} $options
     */
    public function addAction(string $id, string $label, array $options = []): static
    {
        $this->_actions[$id] = [
            'id' => $id,
            'label' => $label,
            'icon' => $options['icon'] ?? 'zap',
            'url' => $options['url'] ?? null,
            'target' => $options['target'] ?? '_self',
            'confirm' => $options['confirm'] ?? null,
            'permission' => $options['permission'] ?? null,
        ];
        return $this;
    }

    /**
     * Информационная панель (поповер) с парами «подпись — значение».
     *
     * @param array<int, array{label: string, value?: mixed, url?: string}> $items
     * @param array{icon?: string, url?: string, permission?: string} $options
     */
    public function addPanel(string $id, string $label, array $items = [], array $options = []): static
    {
        $this->_panels[$id] = [
            'id' => $id,
            'label' => $label,
            'icon' => $options['icon'] ?? 'layers',
            'url' => $options['url'] ?? null,
            'permission' => $options['permission'] ?? null,
            'items' => array_values($items),
        ];
        return $this;
    }

    public function setContext(string $key, mixed $value): static
    {
        $this->_context[$key] = $value;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->_context;
    }

    /** @return array<int, array<string, mixed>> */
    public function getActions(): array
    {
        return array_values(array_filter(
            $this->_actions,
            fn(array $a) => empty($a['permission']) || $this->can($a['permission'])
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function getPanels(): array
    {
        return array_values(array_filter(
            $this->_panels,
            fn(array $p) => empty($p['permission']) || $this->can($p['permission'])
        ));
    }

    /**
     * Серверные действия, доступные текущему администратору (без handler).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getServerActions(): array
    {
        $out = [];
        foreach ($this->serverActions as $id => $action) {
            $permission = $action['permission'] ?? null;
            if ($permission && !$this->can($permission)) {
                continue;
            }
            $out[] = [
                'id' => (string)$id,
                'label' => (string)($action['label'] ?? $id),
                'icon' => (string)($action['icon'] ?? 'zap'),
                'confirm' => $action['confirm'] ?? null,
                'server' => true,
            ];
        }
        return $out;
    }

    /**
     * Выполнить серверное действие.
     *
     * @return array{ok: bool, message: string}
     */
    public function runServerAction(string $id): array
    {
        $action = $this->serverActions[$id] ?? null;
        if ($action === null || !is_callable($action['handler'] ?? null)) {
            return ['ok' => false, 'message' => 'Действие не найдено'];
        }
        $permission = $action['permission'] ?? null;
        if ($permission && !$this->can($permission)) {
            return ['ok' => false, 'message' => 'Недостаточно прав'];
        }
        try {
            $result = call_user_func($action['handler']);
        } catch (\Throwable $e) {
            Yii::error('AdminBar action ' . $id . ': ' . $e->getMessage(), __METHOD__);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        if ($result === false) {
            return ['ok' => false, 'message' => 'Не удалось выполнить'];
        }
        return ['ok' => true, 'message' => is_string($result) ? $result : 'Готово'];
    }

    // ------------------------------------------------------------------
    // Компоненты админки
    // ------------------------------------------------------------------

    /**
     * Активный компонент админки для класса модели.
     */
    public function findComponent(string $modelClass): ?AdminModel
    {
        static $cache = [];
        if (!array_key_exists($modelClass, $cache)) {
            $cache[$modelClass] = AdminModel::find()
                ->where(['model_class' => $modelClass, 'view' => 1])
                ->one();
        }
        return $cache[$modelClass];
    }
}
