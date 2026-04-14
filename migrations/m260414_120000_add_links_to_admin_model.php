<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m260414_120000_add_links_to_admin_model
 *
 * Добавляет JSON-поле `links` в таблицу `admin_model` для хранения
 * пользовательских ссылок-кнопок компонента.
 */
class m260414_120000_add_links_to_admin_model extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%admin_model}}',
            'links',
            $this->text()->null()->comment('JSON-массив пользовательских ссылок-кнопок компонента')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->dropColumn('{{%admin_model}}', 'links');
    }
}
