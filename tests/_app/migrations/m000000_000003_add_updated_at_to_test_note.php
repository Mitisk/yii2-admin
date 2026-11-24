<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Время правки заметки — для TimestampBehavior (проверка, что переключатель в списке его обновляет).
 */
class m000000_000003_add_updated_at_to_test_note extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%test_note}}', 'updated_at', $this->integer()->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%test_note}}', 'updated_at');
    }
}
