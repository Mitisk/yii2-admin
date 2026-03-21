<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%seo_rules}}`.
 */
class m260323_140518_create_seo_rules_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%seo_rules}}', [
            'id' => $this->primaryKey(),
            'pattern' => $this->string()->notNull()->comment('Regex pattern for URL'),
            'title' => $this->string()->comment('Meta tag: title'),
            'description' => $this->text()->comment('Meta tag: description'),
            'keywords' => $this->string()->comment('Meta tag: keywords'),
            'priority' => $this->integer()->defaultValue(0)->comment('Priority (higher applies first)'),
            'is_active' => $this->boolean()->defaultValue(true)->comment('Is active'),
            'og_title' => $this->string()->comment('OG tag: title'),
            'og_description' => $this->text()->comment('OG tag: description'),
            'og_image' => $this->string()->comment('OG tag: image'),
            'robots' => $this->string()->comment('Meta tag: robots'),
        ]);

        $this->createIndex('idx-seo_rules-is_active', '{{%seo_rules}}', 'is_active');
        $this->createIndex('idx-seo_rules-priority', '{{%seo_rules}}', 'priority');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%seo_rules}}');
    }
}
