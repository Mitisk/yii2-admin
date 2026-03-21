<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%admin_audit_log}}`.
 */
class m260319_120000_create_admin_audit_log_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%admin_audit_log}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->null(),
            'action' => $this->string(16)->notNull(),
            'model_class' => $this->string(255)->notNull(),
            'model_id' => $this->string(64)->notNull(),
            'model_label' => $this->string(255)->null(),
            'component_alias' => $this->string(255)->null(),
            'diff' => $this->text()->null(),
            'ip' => $this->string(45)->null(),
            'user_agent' => $this->string(500)->null(),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex(
            'idx-admin_audit_log-user_id',
            '{{%admin_audit_log}}',
            'user_id'
        );
        $this->createIndex(
            'idx-admin_audit_log-model',
            '{{%admin_audit_log}}',
            ['model_class', 'model_id']
        );
        $this->createIndex(
            'idx-admin_audit_log-created_at',
            '{{%admin_audit_log}}',
            'created_at'
        );
        $this->createIndex(
            'idx-admin_audit_log-action',
            '{{%admin_audit_log}}',
            'action'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%admin_audit_log}}');
    }
}
