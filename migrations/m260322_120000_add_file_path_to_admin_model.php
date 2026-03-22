<?php

use yii\db\Migration;

/**
 * Добавляет колонку file_path в таблицу admin_model.
 * Позволяет задать серверный путь для хранения файлов конкретного компонента.
 */
class m260322_120000_add_file_path_to_admin_model extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(
            '{{%admin_model}}',
            'file_path',
            $this->string(255)->null()->comment('Серверный путь для файлов компонента')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%admin_model}}', 'file_path');
    }
}
