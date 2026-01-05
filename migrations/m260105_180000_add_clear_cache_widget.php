<?php

use yii\db\Migration;

/**
 * Class m260105_180000_add_clear_cache_widget
 */
class m260105_180000_add_clear_cache_widget extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert('{{%admin_widget}}', [
            'alias' => 'IndexClearCacheWidget',
            'user_id' => null,
            'class' => '\\Mitisk\\Yii2Admin\\widgets\\IndexClearCacheWidget',
            'ordering' => 2, // Assuming it should be after IndexUserWidget
            'published' => 1,
            'created_at' => new \yii\db\Expression('NOW()'),
        ]);
        
        // Also add to admin_model to be manageable if needed, checking existing structure
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%admin_widget}}', ['alias' => 'IndexClearCacheWidget']);
    }
}
