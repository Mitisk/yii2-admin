<?php

use yii\db\Migration;

/**
 * Текстовые блоки раздела «Контент».
 */
class m261010_120000_create_content_block_table extends Migration
{
    public function safeUp(): void
    {
        $mysql = $this->db->driverName === 'mysql';
        $options = $mysql ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB' : null;
        // В MySQL TEXT ограничен 64 КБ — для HTML-блоков нужен LONGTEXT; в PostgreSQL TEXT без лимита
        $value = $mysql
            ? $this->db->getSchema()->createColumnSchemaBuilder('longtext')
            : $this->text();

        $this->createTable('{{%content_block}}', [
            'id' => $this->primaryKey(),
            'key' => $this->string(128)->notNull()->comment('Ключ из кода сайта'),
            'name' => $this->string()->notNull()->comment('Название для админки'),
            'type' => $this->string(16)->notNull()->defaultValue('text')->comment('BlockType'),
            'value' => $value->null()->comment('Содержимое'),
            'schema' => $this->text()->null()->comment('JSON: поля пункта списка'),
            'group' => $this->string(64)->notNull()->defaultValue('')->comment('Группа для фильтра'),
            'hint' => $this->string()->null()->comment('Подсказка админу'),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'from_code' => $this->boolean()->notNull()->defaultValue(false)->comment('Создан автоматически из шаблона'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'updated_by' => $this->integer()->null(),
        ], $options);

        $this->createIndex('ux-content_block-key', '{{%content_block}}', 'key', true);
        $this->createIndex('idx-content_block-group', '{{%content_block}}', 'group');

        // Техническая таблица: не предлагать её как компонент админки
        $this->insert('{{%admin_model}}', [
            'name' => 'content_block',
            'alias' => 'content-block-table',
            'table_name' => 'content_block',
            'view' => 0,
            'in_menu' => 0,
            'can_create' => 0,
            'non_encode' => 0,
            'default_sort_direction' => SORT_ASC,
        ]);
    }

    public function safeDown(): void
    {
        $this->delete('{{%admin_model}}', ['table_name' => 'content_block']);
        $this->dropTable('{{%content_block}}');
    }
}
