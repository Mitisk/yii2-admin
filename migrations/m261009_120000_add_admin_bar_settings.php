<?php

use yii\db\Migration;

/**
 * Настройки панели администратора на сайте (раздел ADMIN).
 */
class m261009_120000_add_admin_bar_settings extends Migration
{
    private const ROWS = [
        [
            'attribute' => 'bar_enabled',
            'value' => '1',
            'type' => 'boolean',
            'label' => 'Панель администратора на сайте',
            'description' => 'Показывать залогиненному администратору плавающую панель на страницах сайта (виджет AdminBar в лейауте).',
        ],
        [
            'attribute' => 'bar_mode',
            'value' => 'server',
            'type' => 'string',
            'label' => 'Режим подключения панели',
            'description' => 'server — панель рендерится на сервере; client — страница одинакова для всех, панель подгружается скриптом (для полностраничного кэша).',
        ],
        [
            'attribute' => 'bar_position',
            'value' => 'bottom',
            'type' => 'string',
            'label' => 'Положение панели',
            'description' => 'bottom — внизу по центру, top — вверху по центру.',
        ],
    ];

    public function safeUp(): void
    {
        foreach (self::ROWS as $row) {
            $exists = (new \yii\db\Query())
                ->from('{{%settings}}')
                ->where(['model_name' => 'ADMIN', 'attribute' => $row['attribute']])
                ->exists();
            if ($exists) {
                continue;
            }
            $this->insert('{{%settings}}', $row + ['model_name' => 'ADMIN', 'updated_at' => time()]);
        }
    }

    public function safeDown(): void
    {
        $this->delete('{{%settings}}', [
            'model_name' => 'ADMIN',
            'attribute' => array_column(self::ROWS, 'attribute'),
        ]);
    }
}
