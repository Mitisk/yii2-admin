<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Автор заметки — колонка для поля типа user.
 */
class m000000_000002_add_author_to_test_note extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%test_note}}', 'author_id', $this->integer()->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%test_note}}', 'author_id');
    }
}
