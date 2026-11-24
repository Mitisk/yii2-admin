<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Таблица тестовой сущности «Заметка» (Mitisk\Yii2Admin\tests\_app\models\Note).
 * Применяется только к тестовой БД пакета (tests/bootstrap.php).
 */
class m000000_000001_create_test_note_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%test_note}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'published' => $this->smallInteger()->notNull()->defaultValue(0),
            'archived' => $this->smallInteger()->notNull()->defaultValue(0),
            'tags' => $this->json()->null(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%test_note}}');
    }
}
