<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\assets\AdminBarAsset;
use Mitisk\Yii2Admin\components\AdminBarComponent;
use Mitisk\Yii2Admin\components\AdminBarState;
use Yii;
use yii\base\Widget;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

/**
 * Панель администратора на страницах сайта.
 *
 * Подключение в лейауте сайта перед `</body>`:
 * ```php
 * <?= \Mitisk\Yii2Admin\widgets\AdminBar::widget() ?>
 * ```
 * Для неадмина возвращает пустую строку и не подключает ассеты.
 *
 * Inline-правка атрибута записи в шаблоне сайта:
 * ```php
 * <h1><?= \Mitisk\Yii2Admin\widgets\AdminBar::editable($product, 'name') ?></h1>
 * ```
 *
 * @category Widget
 * @package  Mitisk\Yii2Admin\widgets
 * @author   Mitisk <akimkinpit@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/mitisk/yii2-admin
 */
class AdminBar extends Widget
{
    public const MODE_SERVER = 'server';
    public const MODE_CLIENT = 'client';

    /** @var string|null server|client. Null — из настройки ADMIN.bar_mode. */
    public ?string $mode = null;

    /** @var string|null bottom|top. Null — из настройки ADMIN.bar_position. */
    public ?string $position = null;

    /** @var string Id элемента с JSON-состоянием (server-режим). */
    public string $stateId = 'admin-bar-state';

    public function run(): string
    {
        if (!Yii::$app->has('adminBar') || !Yii::$app->has('adminUser')) {
            Yii::warning('AdminBar: модуль admin не подключён в bootstrap — панель не выводится.', __METHOD__);
            return '';
        }

        // Внутри админки панель не нужна
        $route = (string)(Yii::$app->requestedRoute ?? '');
        if ($route === 'admin' || str_starts_with($route, 'admin/')) {
            return '';
        }

        if (!Yii::$app->settings->get('ADMIN', 'bar_enabled', true)) {
            return '';
        }

        $mode = $this->mode ?? (string)Yii::$app->settings->get('ADMIN', 'bar_mode', self::MODE_SERVER);
        $mode = $mode === self::MODE_CLIENT ? self::MODE_CLIENT : self::MODE_SERVER;

        /** @var AdminBarComponent $bar */
        $bar = Yii::$app->get('adminBar');

        $bundle = Yii::$app->assetManager->getBundle(AdminBarAsset::class);
        $assets = [
            'js' => $bundle->baseUrl . '/js/admin-bar.min.js',
            'css' => $bundle->baseUrl . '/css/admin-bar.min.css',
        ];

        if ($mode === self::MODE_CLIENT) {
            return $this->renderClientLoader($bar, $bundle->baseUrl . '/js/admin-bar-loader.min.js', $assets);
        }

        if (!$bar->isAdmin()) {
            return '';
        }

        // Страница с панелью не должна кэшироваться промежуточными кэшами
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');

        $state = AdminBarState::build($bar);
        $state->assets = $assets;
        if ($this->position !== null) {
            $state->prefs['position'] = $this->position;
        }

        AdminBarAsset::register($this->view);

        return Html::tag('admin-bar', '', ['hidden' => true])
            . Html::script(
                Json::htmlEncode($state->toArray()),
                ['type' => 'application/json', 'id' => $this->stateId]
            );
    }

    /**
     * Client-режим: страница одинакова для всех, панель запрашивает состояние сама.
     *
     * @param array{js: string, css: string} $assets
     */
    private function renderClientLoader(AdminBarComponent $bar, string $loaderUrl, array $assets): string
    {
        $options = [
            'src' => $loaderUrl,
            'defer' => true,
            'data' => [
                'state' => rtrim((string)Yii::$app->request->baseUrl, '/') . '/admin/bar/state/',
                'js' => $assets['js'],
                'css' => $assets['css'],
            ],
        ];
        $model = $bar->getModel();
        if ($model instanceof ActiveRecord) {
            $pk = $model->getPrimaryKey();
            $options['data']['ctx'] = Json::encode([
                'class' => get_class($model),
                'id' => is_array($pk) ? implode('-', $pk) : $pk,
            ]);
        }
        if ($this->position !== null) {
            $options['data']['position'] = $this->position;
        }
        return Html::tag('script', '', $options);
    }

    /**
     * Обёртка для inline-правки атрибута записи. Для неадмина — просто значение.
     *
     * @param ActiveRecord $model     Запись.
     * @param string       $attribute Атрибут.
     * @param string|null  $content   Готовый HTML значения (по умолчанию экранированное значение).
     * @param array        $options   HTML-опции обёртки; `tag` (span), `type` (text|html|block|image).
     */
    public static function editable(ActiveRecord $model, string $attribute, ?string $content = null, array $options = []): string
    {
        $value = $model->getAttribute($attribute);
        if ($content === null) {
            $content = Html::encode((string)$value);
        }

        if (!Yii::$app->has('adminBar')) {
            return $content;
        }
        /** @var AdminBarComponent $bar */
        $bar = Yii::$app->get('adminBar');

        // В client-режиме страница должна быть одинаковой для всех (полностраничный кэш):
        // обёртка выводится всегда, права проверяются на сервере при сохранении.
        $clientMode = (string)Yii::$app->settings->get('ADMIN', 'bar_mode', self::MODE_SERVER) === self::MODE_CLIENT;
        if (!$clientMode && (!$bar->isAdmin() || !$bar->canUpdate($model) || $bar->findComponent(get_class($model)) === null)) {
            return $content;
        }

        $tag = $options['tag'] ?? 'span';
        $type = $options['type'] ?? 'text';
        unset($options['tag'], $options['type']);

        $pk = $model->getPrimaryKey();
        $options['data'] = array_merge($options['data'] ?? [], [
            'ab-model' => get_class($model),
            'ab-id' => is_array($pk) ? implode('-', $pk) : $pk,
            'ab-attr' => $attribute,
            'ab-type' => $type,
            'ab-label' => $model->getAttributeLabel($attribute),
        ]);

        return Html::tag($tag, $content, $options);
    }
}
