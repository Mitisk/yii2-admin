<?php

use yii\db\Migration;

/**
 * Скрывает технические модели из списка admin_model (view = 0).
 * Если запись отсутствует — создаёт её с view = 0.
 */
class m260321_120000_hide_technical_models extends Migration
{
    /**
     * Технические таблицы, которые нужно скрыть.
     */
    private const TECHNICAL_TABLES = [
        'admin_audit_log',
        'seo_rules',
        'admin_user_map',
        'admin_model_info',
    ];

    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        foreach (self::TECHNICAL_TABLES as $tableName) {
            $exists = (new \yii\db\Query())
                ->from('{{%admin_model}}')
                ->where(['table_name' => $tableName])
                ->exists();

            if ($exists) {
                $this->update(
                    '{{%admin_model}}',
                    ['view' => 0],
                    ['table_name' => $tableName],
                );
            } else {
                $this->insert('{{%admin_model}}', [
                    'name' => $tableName,
                    'alias' => str_replace('_', '-', $tableName),
                    'table_name' => $tableName,
                    'view' => 0,
                    'in_menu' => 0,
                    'can_create' => 0,
                    'non_encode' => 0,
                    'default_sort_direction' => SORT_ASC,
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        foreach (self::TECHNICAL_TABLES as $tableName) {
            $this->update(
                '{{%admin_model}}',
                ['view' => 1],
                ['table_name' => $tableName],
            );
        }
    }
}
