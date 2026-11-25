<?php

use yii\db\Migration;

/**
 * Планировщик задач: задачи (консольные команды проекта с расписанием) и журнал запусков.
 */
class m261125_120000_create_admin_cron_tables extends Migration
{
    private const SETTINGS = [
        ['attribute' => 'log_days', 'value' => '14', 'type' => 'integer', 'label' => 'Хранить журнал, дней',
            'description' => 'Записи о запусках старше этого срока удаляются автоматически.'],
        ['attribute' => 'log_rows', 'value' => '200', 'type' => 'integer', 'label' => 'Записей журнала на задачу',
            'description' => 'У каждой задачи остаются последние запуски в пределах этого числа.'],
    ];

    public function safeUp()
    {
        $options = $this->db->driverName === 'mysql' ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB' : null;

        $this->createTable('{{%admin_cron_task}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull()->comment('Название для людей'),
            'command' => $this->string(500)->notNull()->comment('Консольный маршрут Yii и аргументы'),
            'schedule' => $this->string(100)->notNull()->comment('Расписание: 5 полей cron'),
            'enabled' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'timeout' => $this->integer()->notNull()->defaultValue(600)->comment('Лимит времени, секунд'),
            'run_requested_at' => $this->integer()->null()->comment('«Запустить сейчас»'),
            'running_since' => $this->integer()->null()->comment('Идёт с этого момента'),
            'last_run_at' => $this->integer()->null(),
            'last_status' => $this->string(16)->null()->comment('ok | fail | timeout'),
            'last_duration_ms' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);

        $this->createTable('{{%admin_cron_log}}', [
            'id' => $this->primaryKey(),
            'task_id' => $this->integer()->notNull(),
            'started_at' => $this->integer()->notNull(),
            'finished_at' => $this->integer()->null(),
            'duration_ms' => $this->integer()->null(),
            'status' => $this->string(16)->notNull()->comment('ok | fail | timeout'),
            'exit_code' => $this->integer()->null(),
            'source' => $this->string(16)->notNull()->defaultValue('schedule')->comment('schedule | manual'),
            'output' => $this->text()->null()->comment('Вывод команды, обрезанный'),
        ], $options);
        $this->createIndex('idx-admin_cron_log-task-started', '{{%admin_cron_log}}', ['task_id', 'started_at']);
        $this->createIndex('idx-admin_cron_log-started', '{{%admin_cron_log}}', ['started_at']);

        foreach (self::SETTINGS as $row) {
            $exists = (new \yii\db\Query())->from('{{%settings}}')
                ->where(['model_name' => 'CRON', 'attribute' => $row['attribute']])->exists($this->db);
            if (!$exists) {
                $this->insert('{{%settings}}', $row + ['model_name' => 'CRON', 'updated_at' => time()]);
            }
        }
        $hasBlock = (new \yii\db\Query())->from('{{%settings_block}}')->where(['model_name' => 'CRON'])->exists($this->db);
        if (!$hasBlock) {
            $this->insert('{{%settings_block}}', [
                'model_name' => 'CRON',
                'label' => 'Планировщик',
                'description' => 'Журнал запусков задач чистится сам по этим правилам.',
            ]);
        }
    }

    public function safeDown()
    {
        $this->delete('{{%settings_block}}', ['model_name' => 'CRON']);
        $this->delete('{{%settings}}', ['model_name' => 'CRON']);
        $this->dropTable('{{%admin_cron_log}}');
        $this->dropTable('{{%admin_cron_task}}');
    }
}
