<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%admin_model_info}}`.
 */
class m260213_210000_create_admin_model_info_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%admin_model_info}}', [
            'id' => $this->primaryKey(),
            'model_class' => $this->string()->notNull()->unique(),
            'content' => $this->text(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->createIndex(
            '{{%idx-admin_model_info-model_class}}',
            '{{%admin_model_info}}',
            'model_class'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%admin_model_info}}');
    }
}
