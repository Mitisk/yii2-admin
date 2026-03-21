<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Модель аудит-лога действий в админ-панели.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $action
 * @property string      $model_class
 * @property string      $model_id
 * @property string|null $model_label
 * @property string|null $component_alias
 * @property string|null $diff
 * @property string|null $ip
 * @property string|null $user_agent
 * @property int         $created_at
 *
 * @property-read AdminUser|null $user
 */
class AuditLog extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%admin_audit_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['action', 'model_class', 'model_id', 'created_at'], 'required'],
            [['user_id', 'created_at'], 'integer'],
            [['diff'], 'string'],
            [['action'], 'string', 'max' => 16],
            [['model_class', 'model_label', 'component_alias'], 'string', 'max' => 255],
            [['model_id'], 'string', 'max' => 64],
            [['ip'], 'string', 'max' => 45],
            [['user_agent'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'Пользователь',
            'action' => 'Действие',
            'model_class' => 'Модель',
            'model_id' => 'ID записи',
            'model_label' => 'Запись',
            'component_alias' => 'Компонент',
            'diff' => 'Изменения',
            'ip' => 'IP',
            'user_agent' => 'User Agent',
            'created_at' => 'Дата',
        ];
    }

    /**
     * Связь с пользователем.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AdminUser::class, ['id' => 'user_id']);
    }

    /**
     * Декодирует diff из JSON.
     *
     * @return array
     */
    public function getDiffArray(): array
    {
        if (empty($this->diff)) {
            return [];
        }
        return json_decode($this->diff, true) ?: [];
    }

    /**
     * Локализованное название действия.
     *
     * @return string
     */
    public function getActionLabel(): string
    {
        return match ($this->action) {
            'create' => 'создал',
            'update' => 'изменил',
            'delete' => 'удалил',
            default  => $this->action,
        };
    }

    /**
     * CSS-класс для бейджа действия.
     *
     * @return string
     */
    public function getActionBadgeStyle(): string
    {
        return match ($this->action) {
            'create' => 'background:#d1fae5;color:#065f46;',
            'update' => 'background:#e0f2fe;color:#0369a1;',
            'delete' => 'background:#fee2e2;color:#991b1b;',
            default  => 'background:#f1f5f9;color:#475569;',
        };
    }

    /**
     * Ссылка на запись в админке.
     *
     * @return string|null
     */
    public function getModelUrl(): ?string
    {
        if ($this->component_alias && $this->action !== 'delete') {
            return '/admin/' . $this->component_alias
                . '/view/?id=' . $this->model_id;
        }
        return null;
    }
}
