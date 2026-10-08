<?php

use yii\db\Migration;

/**
 * Настройки для самообновления модуля: пути к composer и PHP CLI.
 * Пустые значения означают автопоиск.
 */
class m261008_120000_add_self_update_settings extends Migration
{
    private const ROWS = [
        [
            'attribute' => 'composer_path',
            'label' => 'Путь к composer',
            'description' => 'Для автообновления админки. Пусто — поиск в PATH и в корне проекта. Можно указать composer.phar.',
        ],
        [
            'attribute' => 'php_path',
            'label' => 'Путь к PHP CLI',
            'description' => 'Для автообновления админки. Пусто — автоопределение. Например: /usr/bin/php8.2',
        ],
    ];

    public function safeUp(): void
    {
        foreach (self::ROWS as $row) {
            $exists = (new \yii\db\Query())
                ->from('{{%settings}}')
                ->where(['model_name' => 'GENERAL', 'attribute' => $row['attribute']])
                ->exists();
            if ($exists) {
                continue;
            }
            $this->insert('{{%settings}}', [
                'model_name' => 'GENERAL',
                'attribute' => $row['attribute'],
                'value' => '',
                'type' => 'string',
                'label' => $row['label'],
                'description' => $row['description'],
                'updated_at' => time(),
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->delete('{{%settings}}', [
            'model_name' => 'GENERAL',
            'attribute' => array_column(self::ROWS, 'attribute'),
        ]);
    }
}
