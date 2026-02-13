<?php

use yii\db\Migration;

/**
 * Class m260213_000000_add_attribute_labels_to_admin_model
 */
class m260213_000000_add_attribute_labels_to_admin_model extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%admin_model}}', 'attribute_labels', $this->text()->comment('Custom attribute labels'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%admin_model}}', 'attribute_labels');
    }
}
