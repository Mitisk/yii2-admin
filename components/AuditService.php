<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\components\audit\AuditPresenter;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\AuditLog;
use Yii;
use yii\db\ActiveRecord;

/**
 * Сервис аудит-логирования действий в админ-панели.
 */
class AuditService
{
    /** Что пишется в журнал вместо пароля, ключа или токена. */
    public const HIDDEN_VALUE = '•••';

    /**
     * Записывает действие в аудит-лог.
     *
     * @param string       $action  create|update|delete
     * @param ActiveRecord $model   Модель, над которой действие
     * @param array|null   $changed Изменённые атрибуты (для update)
     *
     * @return void
     */
    public static function log(
        string $action,
        ActiveRecord $model,
        ?array $changed = null
    ): void {
        try {
            $modelClass = get_class($model);
            $pk = $model->getPrimaryKey();
            $modelId = is_array($pk)
                ? implode('-', $pk)
                : (string)$pk;

            // Ищем компонент для label и alias
            $component = AdminModel::find()
                ->where(['model_class' => $modelClass, 'view' => 1])
                ->one();

            $label = self::resolveLabel($model, $component);

            $diff = self::buildDiff($action, $model, $changed);

            $log = new AuditLog();
            $log->user_id = Yii::$app->user->isGuest
                ? null
                : (int)Yii::$app->user->id;
            $log->action = $action;
            $log->model_class = $modelClass;
            $log->model_id = $modelId;
            $log->model_label = $label;
            $log->component_alias = $component->alias ?? null;
            $log->diff = $diff ? json_encode(
                $diff,
                JSON_UNESCAPED_UNICODE
            ) : null;
            $log->ip = Yii::$app->request->getUserIP();
            $log->user_agent = mb_substr(
                (string)Yii::$app->request->getUserAgent(),
                0,
                500
            );
            $log->created_at = time();
            $log->save(false);
        } catch (\Throwable $e) {
            Yii::error(
                'AuditService::log failed: ' . $e->getMessage(),
                'audit'
            );
        }
    }

    /**
     * Событие без записи-модели: очистка журнала, массовая операция, действие из консоли.
     *
     * @param string $action     Код действия (название — настройка модуля auditActions)
     * @param string $modelClass К какому разделу относится (класс модели)
     * @param string $label      Что произошло, одной строкой
     * @param string $modelId    Идентификатор, если он есть
     *
     * @return void
     */
    public static function event(
        string $action,
        string $modelClass,
        string $label,
        string $modelId = '0'
    ): void {
        try {
            $isWeb = Yii::$app instanceof \yii\web\Application;
            $log = new AuditLog();
            $log->user_id = $isWeb && !Yii::$app->user->isGuest
                ? (int)Yii::$app->user->id
                : null;
            $log->action = $action;
            $log->model_class = $modelClass;
            $log->model_id = $modelId;
            $log->model_label = mb_substr($label, 0, 255);
            $log->ip = $isWeb ? Yii::$app->request->getUserIP() : null;
            $log->user_agent = $isWeb
                ? mb_substr((string)Yii::$app->request->getUserAgent(), 0, 500)
                : null;
            $log->created_at = time();
            $log->save(false);
        } catch (\Throwable $e) {
            Yii::error(
                'AuditService::event failed: ' . $e->getMessage(),
                'audit'
            );
        }
    }

    /**
     * Человекочитаемый label записи.
     *
     * @param ActiveRecord      $model
     * @param AdminModel|null   $component
     *
     * @return string
     */
    private static function resolveLabel(
        ActiveRecord $model,
        ?AdminModel $component
    ): string {
        // Приоритет: admin_label из компонента
        if ($component && $component->admin_label) {
            $attr = $component->admin_label;
            if ($model->hasAttribute($attr)) {
                $val = $model->getAttribute($attr);
                if ($val !== null && $val !== '') {
                    return (string)$val;
                }
            }
        }

        // Fallback: name, title, или ID
        foreach (['name', 'title', 'label'] as $attr) {
            if ($model->hasAttribute($attr)) {
                $val = $model->getAttribute($attr);
                if ($val !== null && $val !== '') {
                    return (string)$val;
                }
            }
        }

        $pk = $model->getPrimaryKey();
        return 'ID: ' . (is_array($pk)
            ? implode('-', $pk)
            : (string)$pk);
    }

    /**
     * Строит diff изменений.
     *
     * @param string       $action
     * @param ActiveRecord $model
     * @param array|null   $changed
     *
     * @return array|null
     */
    private static function buildDiff(
        string $action,
        ActiveRecord $model,
        ?array $changed
    ): ?array {
        if ($action === 'delete') {
            return null;
        }

        if ($action === 'create') {
            $attrs = $model->getAttributes();
            $diff = [];
            foreach ($attrs as $key => $value) {
                if ($value !== null && $value !== '') {
                    $diff[$key] = [null, self::storable((string)$key, $value)];
                }
            }
            return $diff ?: null;
        }

        // update
        if (empty($changed)) {
            return null;
        }

        $diff = [];
        foreach ($changed as $attr => $oldValue) {
            $newValue = $model->getAttribute($attr);
            if (!self::sameValue($oldValue, $newValue)) {
                $diff[$attr] = [
                    self::storable((string)$attr, $oldValue),
                    self::storable((string)$attr, $newValue),
                ];
            }
        }

        return $diff ?: null;
    }

    /**
     * Равны ли значения атрибута для журнала: null и '' равны, 1 и '1' равны,
     * массивы и объекты (JSON-колонки) сравниваются по JSON.
     *
     * @param mixed $old
     * @param mixed $new
     *
     * @return bool
     */
    public static function sameValue(mixed $old, mixed $new): bool
    {
        return self::comparable($old) === self::comparable($new);
    }

    /**
     * Значение для журнала: пароли, ключи и токены в журнал не попадают —
     * видно только, что поле менялось.
     */
    private static function storable(string $attribute, mixed $value): mixed
    {
        if ($value === null || $value === ''
            || !AuditPresenter::isSensitive($attribute)
        ) {
            return $value;
        }

        return self::HIDDEN_VALUE;
    }

    private static function comparable(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return (string)json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string)$value;
    }
}
