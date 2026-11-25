<?php

use yii\db\Migration;

/**
 * Планировщик: задачи из кода проекта (Module::$cronTasks) и режим журнала «только ошибки».
 */
class m261125_150000_add_code_tasks_to_admin_cron extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%admin_cron_task}}', 'code_key', $this->string(64)->null()->comment('Ключ задачи из кода проекта; NULL — задача создана в админке')->after('id'));
        $this->addColumn('{{%admin_cron_task}}', 'log_mode', $this->string(16)->notNull()->defaultValue('all')->comment('all | failures')->after('timeout'));
        $this->createIndex('ux-admin_cron_task-code_key', '{{%admin_cron_task}}', 'code_key', true);
    }

    public function safeDown()
    {
        $this->delete('{{%admin_cron_task}}', ['not', ['code_key' => null]]);
        $this->dropIndex('ux-admin_cron_task-code_key', '{{%admin_cron_task}}');
        $this->dropColumn('{{%admin_cron_task}}', 'log_mode');
        $this->dropColumn('{{%admin_cron_task}}', 'code_key');
    }
}
