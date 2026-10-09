<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Страницы раздела «Контент»: дерево страниц, авторедиректы старых адресов, настройки.
 */
class m261012_120000_create_page_tables extends Migration
{
    private const SETTINGS = [
        [
            'attribute' => 'pages_enabled',
            'value' => '1',
            'type' => 'boolean',
            'label' => 'Страницы на сайте',
            'description' => 'Отдавать страницы раздела «Контент» по их адресам (правило URL и контроллер модуля).',
        ],
        [
            'attribute' => 'pages_iframe_hosts',
            'value' => 'youtube.com, youtu.be, vk.com, vkvideo.ru, rutube.ru, yandex.ru, google.com',
            'type' => 'string',
            'label' => 'Домены для встраиваний (iframe)',
            'description' => 'Через запятую. Видео и карты только с этих доменов и их поддоменов остаются в тексте страниц.',
        ],
    ];

    public function safeUp(): void
    {
        $mysql = $this->db->driverName === 'mysql';
        $options = $mysql ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB' : null;
        $body = $mysql ? $this->db->getSchema()->createColumnSchemaBuilder('longtext') : $this->text();

        $this->createTable('{{%page}}', [
            'id' => $this->primaryKey(),
            'parent_id' => $this->integer()->null(),
            'slug' => $this->string(128)->notNull(),
            'path' => $this->string(512)->notNull()->comment('Полный адрес без слешей по краям'),
            'title' => $this->string()->notNull(),
            'body' => $body->null(),
            'excerpt' => $this->text()->null(),
            'template' => $this->string(64)->notNull()->defaultValue('default'),
            'status' => $this->string(16)->notNull()->defaultValue('draft'),
            'published_at' => $this->integer()->null(),
            'sort' => $this->integer()->notNull()->defaultValue(0),
            'seo_title' => $this->string()->null(),
            'seo_description' => $this->text()->null(),
            'seo_keywords' => $this->string()->null(),
            'og_image_id' => $this->integer()->null(),
            'canonical' => $this->string(2048)->null(),
            'noindex' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'created_by' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
        ], $options);
        $this->createIndex('ux-page-path', '{{%page}}', 'path', true);
        $this->createIndex('ux-page-parent-slug', '{{%page}}', ['parent_id', 'slug'], true);
        $this->createIndex('idx-page-status', '{{%page}}', ['status', 'published_at']);

        $this->createTable('{{%page_redirect}}', [
            'id' => $this->primaryKey(),
            'from_path' => $this->string(512)->notNull(),
            'to_path' => $this->string(512)->notNull(),
            'code' => $this->smallInteger()->notNull()->defaultValue(301),
            'page_id' => $this->integer()->null()->comment('Null — ручной редирект'),
            'hits' => $this->integer()->notNull()->defaultValue(0),
            'last_hit_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('ux-page_redirect-from', '{{%page_redirect}}', 'from_path', true);
        $this->createIndex('idx-page_redirect-page', '{{%page_redirect}}', 'page_id');

        foreach (self::SETTINGS as $row) {
            $exists = (new \yii\db\Query())->from('{{%settings}}')
                ->where(['model_name' => 'ADMIN', 'attribute' => $row['attribute']])->exists();
            if (!$exists) {
                $this->insert('{{%settings}}', $row + ['model_name' => 'ADMIN', 'updated_at' => time()]);
            }
        }

        // Технические таблицы: не предлагать как компоненты админки
        foreach (['page', 'page_redirect'] as $table) {
            $this->insert('{{%admin_model}}', [
                'name' => $table, 'alias' => str_replace('_', '-', $table) . '-table', 'table_name' => $table,
                'view' => 0, 'in_menu' => 0, 'can_create' => 0, 'non_encode' => 0, 'default_sort_direction' => SORT_ASC,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->delete('{{%admin_model}}', ['table_name' => ['page', 'page_redirect']]);
        $this->delete('{{%settings}}', ['model_name' => 'ADMIN', 'attribute' => array_column(self::SETTINGS, 'attribute')]);
        $this->dropTable('{{%page_redirect}}');
        $this->dropTable('{{%page}}');
    }
}
