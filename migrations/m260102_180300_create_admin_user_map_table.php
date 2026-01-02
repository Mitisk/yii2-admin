<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%admin_user_map}}`.
 */
class m260102_180300_create_admin_user_map_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%admin_user_map}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(),
            'form' => $this->string(),
            'view' => $this->string(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%admin_user_map}}');
    }
}
