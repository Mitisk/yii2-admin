<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\AdminBarComponent;
use Mitisk\Yii2Admin\components\AdminBarState;
use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\enums\BlockType;
use Mitisk\Yii2Admin\models\ContentBlock;
use Yii;
use yii\db\ActiveRecord;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\Response;

/**
 * AJAX-эндпоинты панели администратора на сайте (`/admin/bar/...`).
 *
 * Все ответы — JSON. Неадмину отвечает 401 без редиректа на логин.
 */
class BarController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'state' => ['GET'],
                    'action' => ['POST'],
                    'attribute' => ['POST'],
                    'block' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!$this->getBar()->isAdmin()) {
            Yii::$app->response->statusCode = 401;
            Yii::$app->response->data = ['ok' => false, 'message' => 'Требуется вход в админку'];
            return false;
        }
        return true;
    }

    /**
     * Состояние панели (client-режим).
     *
     * GET-параметры: `url` — адрес страницы, `ctx` — JSON `{class, id}` контекстной модели.
     */
    public function actionState(): array
    {
        $url = Yii::$app->request->get('url');
        $ctx = Yii::$app->request->get('ctx');
        $hint = null;
        if (is_string($ctx) && $ctx !== '') {
            $decoded = json_decode($ctx, true);
            $hint = is_array($decoded) ? $decoded : null;
        }

        $state = AdminBarState::build($this->getBar(), is_string($url) ? $url : null, $hint);
        return $state->toArray();
    }

    /**
     * Выполнить серверное действие из регистра `AdminBarComponent::$serverActions`.
     */
    public function actionAction(): array
    {
        $id = (string)Yii::$app->request->post('id', '');
        if ($id === '') {
            return ['ok' => false, 'message' => 'Не указано действие'];
        }
        return $this->getBar()->runServerAction($id);
    }

    /**
     * Inline-правка атрибута записи с валидацией и аудитом.
     *
     * POST: `model` (FQCN), `id`, `attr`, `value`.
     */
    public function actionAttribute(): array
    {
        $request = Yii::$app->request;
        $class = str_replace('\\\\', '\\', (string)$request->post('model', ''));
        $id = $request->post('id');
        $attr = (string)$request->post('attr', '');
        $value = $request->post('value');

        if ($class === '' || $id === null || $id === '' || $attr === '') {
            return ['ok' => false, 'message' => 'Неполные данные'];
        }
        if (!class_exists($class) || !is_subclass_of($class, ActiveRecord::class)) {
            return ['ok' => false, 'message' => 'Модель не найдена'];
        }

        $bar = $this->getBar();
        if ($bar->findComponent($class) === null) {
            return ['ok' => false, 'message' => 'Модель не управляется админкой'];
        }
        if (!$bar->canUpdate($class)) {
            Yii::$app->response->statusCode = 403;
            return ['ok' => false, 'message' => 'Нет права на редактирование'];
        }

        /** @var ActiveRecord|null $model */
        $model = $class::findOne($id);
        if ($model === null) {
            Yii::$app->response->statusCode = 404;
            return ['ok' => false, 'message' => 'Запись не найдена'];
        }
        if (!$model->hasAttribute($attr) || !in_array($attr, $model->safeAttributes(), true)) {
            return ['ok' => false, 'message' => 'Атрибут недоступен для правки'];
        }

        $old = $model->getAttribute($attr);
        $model->setAttribute($attr, is_string($value) ? trim($value) : $value);

        if (!$model->validate([$attr])) {
            return [
                'ok' => false,
                'message' => implode(' ', $model->getErrors($attr)),
                'value' => $old,
            ];
        }
        if (!$model->save(false, [$attr])) {
            return ['ok' => false, 'message' => 'Не удалось сохранить', 'value' => $old];
        }

        if ((string)$old !== (string)$model->getAttribute($attr)) {
            AuditService::log('update', $model, [$attr => $old]);
        }

        $new = $model->getAttribute($attr);
        return [
            'ok' => true,
            'message' => 'Сохранено',
            'value' => $new,
            'html' => Html::encode((string)$new),
        ];
    }

    /**
     * Inline-правка текстового блока раздела «Контент».
     *
     * POST: `key`, `value`. Только тип `text`; остальные типы правятся в модальном окне.
     */
    public function actionBlock(): array
    {
        $bar = $this->getBar();
        if (!$bar->can('editContent')) {
            Yii::$app->response->statusCode = 403;
            return ['ok' => false, 'message' => 'Нет права на правку контента'];
        }
        $request = Yii::$app->request;
        $key = (string)$request->post('key', '');
        $value = $request->post('value');
        if ($key === '' || !is_string($value)) {
            return ['ok' => false, 'message' => 'Неполные данные'];
        }
        $block = ContentBlock::find()->byKey($key)->one();
        if ($block === null) {
            Yii::$app->response->statusCode = 404;
            return ['ok' => false, 'message' => 'Блок не найден'];
        }
        if ($block->getBlockType() !== BlockType::Text) {
            return ['ok' => false, 'message' => 'Этот блок правится в окне редактирования'];
        }
        $old = $block->value;
        $block->value = trim($value);
        if (!$block->validate(['value'])) {
            return ['ok' => false, 'message' => implode(' ', $block->getErrors('value')), 'value' => (string)$old];
        }
        $block->save(false, ['value', 'updated_at', 'updated_by']);
        if ((string)$old !== (string)$block->value) {
            AuditService::log('update', $block, ['value' => $old]);
        }
        return ['ok' => true, 'message' => 'Сохранено', 'value' => (string)$block->value];
    }

    private function getBar(): AdminBarComponent
    {
        /** @var AdminBarComponent $bar */
        $bar = Yii::$app->get('adminBar');
        return $bar;
    }
}
