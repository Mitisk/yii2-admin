<?php

use yii\db\Migration;

/**
 * Class m251129_184900_init_full_admin_panel
 */
class m251129_184900_init_full_admin_panel extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB';
        }

        // ============================================================
        // 1. Создание таблиц (Структура)
        // ============================================================

        // --- Table: user ---
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'online_at' => $this->integer()->defaultValue(null),
            'username' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->defaultValue(null),
            'password_hash' => $this->string(255)->notNull(),
            'email' => $this->string(255)->notNull(),
            'name' => $this->string(255)->defaultValue(null),
            'image' => $this->string(255)->defaultValue(null),
            'status' => $this->smallInteger()->notNull()->defaultValue(0),
            'auth_type' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('Тип аутентификации (0,1,2)'),
            'mfa_secret' => $this->string(255)->defaultValue(null)->comment('Секрет для MFA'),
        ], $tableOptions);

        // --- Table: admin_controller_map ---
        $this->createTable('{{%admin_controller_map}}', [
            'id' => $this->primaryKey(),
            'controller_id' => $this->string(64)->notNull(),
            'class' => $this->string(255)->notNull(),
            'config' => $this->json()->defaultValue(null),
            'enabled' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'created_at' => $this->integer()->unsigned()->defaultValue(null),
            'updated_at' => $this->integer()->unsigned()->defaultValue(null),
        ], $tableOptions);

        // --- Table: admin_model ---
        $this->createTable('{{%admin_model}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->defaultValue(null),
            'alias' => $this->string(255)->defaultValue(null),
            'table_name' => $this->string(255)->notNull(),
            'model_class' => $this->string(255)->defaultValue(null),
            'admin_label' => $this->string(255)->defaultValue(null),
            'list' => $this->text(),
            'data' => $this->text(),
            'view' => $this->tinyInteger(1)->defaultValue(1),
            'in_menu' => $this->tinyInteger(1)->defaultValue(0),
            'can_create' => $this->tinyInteger(1)->defaultValue(0),
            'non_encode' => $this->tinyInteger(1)->defaultValue(0),
            'default_sort_attribute' => $this->string(255)->defaultValue(null)->comment('Поле для сортировки по умолчанию'),
            'default_sort_direction' => $this->tinyInteger(1)->defaultValue(4)->comment('Направление: 4=ASC, 3=DESC'),
        ], $tableOptions);

        // --- Table: admin_note ---
        $this->createTable('{{%admin_note}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'text' => $this->text(), // longtext в MySQL
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ], $tableOptions);

        // --- Table: admin_widget ---
        $this->createTable('{{%admin_widget}}', [
            'id' => $this->primaryKey(),
            'alias' => $this->string(255)->notNull(),
            'user_id' => $this->integer()->defaultValue(null),
            'class' => $this->string(500)->notNull(),
            'ordering' => $this->integer()->notNull()->defaultValue(0),
            'published' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ], $tableOptions);

        // --- Table: admin_widget_component ---
        $this->createTable('{{%admin_widget_component}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'component_alias' => $this->string(128)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        // --- Table: email_templates ---
        $this->createTable('{{%email_templates}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(100)->notNull()->comment('Уникальный идентификатор'),
            'name' => $this->string(255)->notNull()->comment('Название для админа'),
            'subject' => $this->string(255)->notNull()->comment('Тема письма'),
            'body' => $this->text()->comment('HTML тело письма'),
            'params' => $this->json()->defaultValue(null)->comment('JSON конфигурация переменных'),
            'active' => $this->tinyInteger(1)->defaultValue(1)->comment('Активность'),
            'created_at' => $this->integer()->defaultValue(null),
            'updated_at' => $this->integer()->defaultValue(null),
        ], $tableOptions);

        // --- Table: file ---
        $this->createTable('{{%file}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'filename' => $this->string(255)->defaultValue(null),
            'class_name' => $this->string(255)->defaultValue(null),
            'item_id' => $this->integer()->unsigned()->defaultValue(null),
            'field_name' => $this->string(255)->defaultValue(null),
            'uploaded_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'alt_attribute' => $this->string(255)->defaultValue(null),
            'file_size' => $this->bigInteger()->unsigned()->notNull(),
            'mime_type' => $this->string(100)->notNull(),
            'path' => $this->string(1000)->notNull(),
            'storage_type' => $this->string(20)->defaultValue('local'),
        ], $tableOptions);

        // --- Table: menu ---
        $this->createTable('{{%menu}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->defaultValue(null)->comment('Название'),
            'alias' => $this->string(255)->defaultValue(null)->comment('Алиас'),
            'data' => $this->text()->comment('Данные'),
            'not_editable' => $this->tinyInteger(1)->defaultValue(0),
            'ordering' => $this->integer()->defaultValue(null)->comment('Сортировка'),
        ], $tableOptions);

        // --- Table: settings ---
        $this->createTable('{{%settings}}', [
            'id' => $this->primaryKey(),
            'model_name' => $this->string(255)->notNull()->comment('Имя модели'),
            'attribute' => $this->string(255)->notNull()->comment('Название параметра'),
            'value' => $this->text()->comment('Значение'),
            'type' => $this->string(32)->notNull()->defaultValue('string')->comment('Тип данных'),
            'label' => $this->string(255)->defaultValue(null)->comment('Человекочитаемое название'),
            'description' => $this->text()->comment('Описание параметра'),
            'updated_at' => $this->integer()->defaultValue(null)->comment('Время последнего изменения'),
        ], $tableOptions);

        // --- Table: settings_block ---
        $this->createTable('{{%settings_block}}', [
            'id' => $this->primaryKey(),
            'model_name' => $this->string(255)->defaultValue(null)->comment('Имя модели'),
            'label' => $this->string(255)->defaultValue(null)->comment('Заголовок'),
            'description' => $this->text()->comment('Описание'),
        ], $tableOptions);

        // --- RBAC Tables (Standard Yii2 structure) ---
        $this->createTable('{{%auth_rule}}', [
            'name' => $this->string(64)->notNull(),
            'data' => $this->binary(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
            'PRIMARY KEY ([[name]])',
        ], $tableOptions);

        $this->createTable('{{%auth_item}}', [
            'name' => $this->string(64)->notNull(),
            'type' => $this->smallInteger()->notNull(),
            'description' => $this->text(),
            'rule_name' => $this->string(64),
            'data' => $this->binary(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
            'ordering' => $this->integer()->defaultValue(null),
            'PRIMARY KEY ([[name]])',
        ], $tableOptions);

        $this->createTable('{{%auth_item_child}}', [
            'parent' => $this->string(64)->notNull(),
            'child' => $this->string(64)->notNull(),
            'PRIMARY KEY ([[parent]], [[child]])',
        ], $tableOptions);

        $this->createTable('{{%auth_assignment}}', [
            'item_name' => $this->string(64)->notNull(),
            'user_id' => $this->string(64)->notNull(),
            'created_at' => $this->integer(),
            'PRIMARY KEY ([[item_name]], [[user_id]])',
        ], $tableOptions);


        // ============================================================
        // 2. Индексы и Внешние ключи (Оптимизация)
        // ============================================================

        // --- admin_controller_map ---
        $this->createIndex('idx_controller_id', '{{%admin_controller_map}}', 'controller_id', true);
        $this->createIndex('idx_admin_ctrl_map_enabled', '{{%admin_controller_map}}', 'enabled');

        // --- admin_model ---
        $this->createIndex('idx_am_table_name', '{{%admin_model}}', 'table_name', true);
        $this->createIndex('idx_am_alias', '{{%admin_model}}', 'alias');
        $this->createIndex('idx_am_view_in_menu', '{{%admin_model}}', ['view', 'in_menu']);

        // --- admin_note ---
        $this->createIndex('idx_admin_note_user', '{{%admin_note}}', 'user_id');
        $this->addForeignKey('fk_admin_note_user', '{{%admin_note}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        // --- admin_widget ---
        $this->createIndex('idx_user_id', '{{%admin_widget}}', 'user_id');
        $this->createIndex('idx_published', '{{%admin_widget}}', 'published');
        $this->createIndex('idx_ordering', '{{%admin_widget}}', 'ordering');
        $this->createIndex('idx_widget_composite', '{{%admin_widget}}', ['user_id', 'published', 'ordering']);
        // Индекс с ограничением длины для MySQL вручную, т.к. Yii createIndex не всегда поддерживает длину
        $this->execute("CREATE INDEX idx_widget_class ON {{%admin_widget}} (class(50))");

        // --- admin_widget_component ---
        $this->createIndex('idx_awc_user', '{{%admin_widget_component}}', 'user_id');
        $this->createIndex('idx_awc_alias', '{{%admin_widget_component}}', 'component_alias');
        $this->addForeignKey('fk_awc_user', '{{%admin_widget_component}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        // --- auth RBAC FKs ---
        $this->createIndex('idx-auth_item-type', '{{%auth_item}}', 'type');
        $this->addForeignKey('auth_item_ibfk_1', '{{%auth_item}}', 'rule_name', '{{%auth_rule}}', 'name', 'SET NULL', 'CASCADE');

        $this->addForeignKey('auth_item_child_ibfk_1', '{{%auth_item_child}}', 'parent', '{{%auth_item}}', 'name', 'CASCADE', 'CASCADE');
        $this->addForeignKey('auth_item_child_ibfk_2', '{{%auth_item_child}}', 'child', '{{%auth_item}}', 'name', 'CASCADE', 'CASCADE');

        $this->createIndex('idx-auth_assignment-user_id', '{{%auth_assignment}}', 'user_id');
        $this->addForeignKey('auth_assignment_ibfk_1', '{{%auth_assignment}}', 'item_name', '{{%auth_item}}', 'name', 'CASCADE', 'CASCADE');

        // --- email_templates ---
        $this->createIndex('idx-email_templates-slug', '{{%email_templates}}', 'slug', true);

        // --- file ---
        $this->createIndex('idx_file_polymorphic', '{{%file}}', ['class_name', 'item_id', 'field_name']);

        // --- menu ---
        $this->createIndex('idx_menu_alias', '{{%menu}}', 'alias');
        $this->createIndex('idx_menu_ordering', '{{%menu}}', 'ordering');

        // --- settings ---
        $this->createIndex('idx-settings-model-attribute', '{{%settings}}', ['model_name', 'attribute'], true);

        // --- user ---
        $this->createIndex('idx_user_username', '{{%user}}', 'username', true);
        $this->createIndex('idx_user_email', '{{%user}}', 'email', true);
        $this->createIndex('idx_user_status', '{{%user}}', 'status');
        $this->createIndex('idx_user_auth_key', '{{%user}}', 'auth_key');


        // ============================================================
        // 3. Заполнение данных (Data Dump)
        // ============================================================

        // Users
        $this->batchInsert('{{%user}}',
            ['id', 'created_at', 'updated_at', 'online_at', 'username', 'auth_key', 'password_hash', 'email', 'name', 'image', 'status', 'auth_type', 'mfa_secret'],
            [
                [1, 1694964503, 1764430391, 1764430344, 'admin', null, '$2y$13$lEbIH94C8DZ76HEP2/X/Jefp/IwAWmw4kSeDs7L7lm7YkdaZrUubm', 'admin@keypage.ru', 'Администратор', null, 1, 0, null],
            ]
        );

        // Admin Model
        $this->batchInsert('{{%admin_model}}',
            ['id', 'name', 'alias', 'table_name', 'model_class', 'admin_label', 'list', 'data', 'view', 'in_menu', 'can_create', 'non_encode', 'default_sort_attribute', 'default_sort_direction'],
            [
                [1, null, null, 'auth_assignment', null, null, null, null, 0, 0, 0, 0, null, 4],
                [2, null, null, 'admin_model', null, null, null, null, 0, 0, 0, 0, null, 4],
                [3, null, null, 'auth_item', null, null, null, null, 0, 0, 0, 0, null, 4],
                [4, null, null, 'auth_item_child', null, null, null, null, 0, 0, 0, 0, null, 4],
                [5, null, null, 'auth_rule', null, null, null, null, 0, 0, 0, 0, null, 4],
                [6, 'Список миграций', null, 'migration', null, null, null, null, 0, 0, 0, 0, null, 4],
                [7, 'Пользователи', null, 'user', null, null, null, null, 1, 0, 0, 0, null, 4],
                [9, 'Меню сайта', null, 'menu', 'app\\modules\\admin\\models\\Menu', null, null, null, 0, 0, 0, 0, null, 4],
                [13, 'Файлы', null, 'file', null, null, null, null, 0, 0, 0, 0, null, 4],
                [14, 'Основные настройки', null, 'settings', 'Mitisk\\Yii2Admin\\models\\Settings', null, null, null, 0, 0, 0, 0, null, 4],
                [15, 'Почта', null, 'mail_templates', 'Mitisk\\Yii2Admin\\models\\MailTemplate', null, null, null, 0, 0, 0, 0, null, 4],
                [16, 'admin_component', null, 'admin_component', null, null, null, null, 0, 0, 0, 0, null, 4],
                [17, 'settings_block', null, 'settings_block', null, null, null, null, 0, 0, 0, 0, null, 4],
                [18, 'admin_controller_map', null, 'admin_controller_map', null, null, null, null, 0, 0, 0, 0, null, 4],
                [19, 'admin_widget', null, 'admin_widget', null, null, null, null, 0, 0, 0, 0, null, 4],
                [20, 'admin_note', null, 'admin_note', null, null, null, null, 0, 0, 0, 0, null, 4],
                [21, 'admin_widget_component', null, 'admin_widget_component', null, null, null, null, 0, 0, 0, 0, null, 4],
                [22, 'email_templates', null, 'email_templates', null, null, null, null, 0, 0, 0, 0, null, 4],
                [23, 'admin_user_map', null, 'admin_user_map', null, null, null, null, 0, 0, 0, 0, null, 4],
            ]
        );

        // Admin Widget
        $this->batchInsert('{{%admin_widget}}',
            ['id', 'alias', 'user_id', 'class', 'ordering', 'published', 'created_at', 'updated_at'],
            [
                [1, 'IndexUserWidget', null, '\\Mitisk\\Yii2Admin\\widgets\\IndexUserWidget', 1, 1, '2025-10-18 13:28:06', '2025-10-18 13:57:06'],
            ]
        );

        // Auth Items
        $this->batchInsert('{{%auth_item}}',
            ['name', 'type', 'description', 'rule_name', 'data', 'created_at', 'updated_at', 'ordering'],
            [
                ['accessAdmin', 2, 'Доступ к админ панели', null, null, 1757175754, 1757175754, null],
                ['admin', 1, 'Администратор', null, null, 1757175754, 1757175754, 2],
                ['createUsers', 2, 'Создание пользователей', null, null, 1757175754, 1757175754, null],
                ['deleteUsers', 2, 'Удаление пользователей', null, null, 1757175754, 1757175754, null],
                ['guest', 1, 'Гость', null, null, 1757175754, 1757790123, 6],
                ['manager', 1, 'Менеджер', null, null, 1757175754, 1759242883, 4],
                ['manageRoles', 2, 'Управление ролями', null, null, 1757175754, 1757175754, null],
                ['manageSystem', 2, 'Управление системой', null, null, 1757175754, 1757175754, null],
                ['manageUserRoles', 2, 'Управление ролями пользователей', null, null, 1757175754, 1757175754, null],
                ['moderator', 1, 'Модератор', null, null, 1757175754, 1757782437, 3],
                ['superAdmin', 2, 'Супер администратор', null, null, 1757175754, 1757175754, null],
                ['superAdminRole', 1, 'Супер администратор', null, null, 1757175754, 1757175754, 1],
                ['updateUsers', 2, 'Редактирование пользователей', null, null, 1757175754, 1757175754, null],
                ['user', 1, 'Обычный пользователь', null, null, 1757175754, 1757175754, 5],
                ['viewReports', 2, 'Просмотр отчетов', null, null, 1757175754, 1757175754, null],
                ['viewUsers', 2, 'Просмотр пользователей', null, null, 1757175754, 1757175754, null],
            ]
        );

        // Auth Item Child
        $this->batchInsert('{{%auth_item_child}}',
            ['parent', 'child'],
            [
                ['user', 'accessAdmin'],
                ['superAdminRole', 'admin'],
                ['manager', 'createUsers'],
                ['admin', 'deleteUsers'],
                ['admin', 'manager'],
                ['admin', 'manageRoles'],
                ['admin', 'manageSystem'],
                ['admin', 'manageUserRoles'],
                ['manager', 'moderator'],
                ['superAdminRole', 'superAdmin'],
                ['manager', 'updateUsers'],
                ['moderator', 'updateUsers'],
                ['moderator', 'user'],
                ['moderator', 'viewReports'],
                ['moderator', 'viewUsers'],
            ]
        );

        // Auth Assignment
        $this->batchInsert('{{%auth_assignment}}',
            ['item_name', 'user_id', 'created_at'],
            [
                ['superAdminRole', '1', 1757175759],
            ]
        );

        // Email Templates
        $this->batchInsert('{{%email_templates}}',
            ['id', 'slug', 'name', 'subject', 'body', 'params', 'active', 'created_at', 'updated_at'],
            [
                [2, 'new_user_password', 'Новый пароль', 'Ваш новый пароль', '<p>На ваш аккаунт установлен новый пароль: {{PASSWORD}}</p>', '{"PASSWORD": {"desc": "Новый пароль", "required": "1"}}', 1, 1764420581, 1764420601],
                [3, 'registration', 'Регистрация пользователя', 'Регистрация', '<p>Спасибо за регистрацию на нашем портале!</p>', '[]', 1, 1764424957, 1764424957],
            ]
        );

        // Menu
        $this->batchInsert('{{%menu}}',
            ['id', 'name', 'alias', 'data', 'not_editable', 'ordering'],
            [
                [1, 'Административное', 'admin', '[{"text":"Главная","href":"/admin/","icon":"fas fa-home","target":"_self","rule":"accessAdmin","title":""},{"text":"Пользователи","href":"#","icon":"fas fa-user-friends","target":"_self","rule":"viewUsers","title":"","children":[{"text":"Все пользователи","href":"/admin/user/","icon":"empty","target":"_self","rule":"viewUsers","title":""},{"text":"Роли","href":"/admin/role/","icon":"empty","target":"_self","rule":"manageRoles","title":""}]}]', 1, 1],
            ]
        );

        // Settings Block
        $this->batchInsert('{{%settings_block}}',
            ['id', 'model_name', 'label', 'description'],
            [
                [1, 'GENERAL', 'Основные', 'Это основные настройки'],
                [5, 'Mitisk\\Yii2Admin\\models\\File', 'Файлы', null],
                [6, 'Mitisk\\Yii2Admin\\models\\AdminUser', 'Пользователи', null],
            ]
        );

        // Settings
        $this->batchInsert('{{%settings}}',
            ['id', 'model_name', 'attribute', 'value', 'type', 'label', 'description', 'updated_at'],
            [
                [1, 'GENERAL', 'api_key', null, 'string', 'API ключ', 'Позволяет получать расширения', 1751803550],
                [2, 'GENERAL', 'admin_email', null, 'string', 'Email адрес администратора', '', 1764424973],
                [3, 'GENERAL', 'timezone', 'Europe/Moscow', 'string', 'Временная зона', '', 1764424973],
                [4, 'GENERAL', 'site_name', null, 'string', 'Название сайта', '', 1764424973],
                [5, 'ADMIN', 'logo', null, 'file', 'Логотип', 'Предпочитаемый размер 154х52 px', 1759332896],
                [6, 'Mitisk\\Yii2Admin\\models\\MailTemplate', 'mailserver_host', null, 'string', 'Почтовый сервер', '', 1764424973],
                [7, 'Mitisk\\Yii2Admin\\models\\MailTemplate', 'mailserver_port', null, 'integer', 'Порт', null, 1764424973],
                [8, 'Mitisk\\Yii2Admin\\models\\MailTemplate', 'mailserver_login', null, 'string', 'Логин', '', 1764424973],
                [9, 'Mitisk\\Yii2Admin\\models\\MailTemplate', 'mailserver_password', null, 'string', 'Пароль', '', 1764424973],
                [10, 'Mitisk\\Yii2Admin\\models\\MailTemplate', 'mailserver_from_name', null, 'string', 'Название отправителя', '', 1764424973],
                [11, 'Mitisk\\Yii2Admin\\models\\AdminUser', 'mail_template_registration', 'registration', 'mail_template', 'Шаблон письма регистрации', null, 1764424973],
                [12, 'Mitisk\\Yii2Admin\\models\\AdminUser', 'mail_template_new_password', 'new_user_password', 'mail_template', 'Шаблон письма нового пароля', null, 1764424973],
                [13, 'Mitisk\\Yii2Admin\\models\\AdminUser', 'mail_template_restore_password', '', 'mail_template', 'Шаблон письма восстановления пароля', null, 1764424973],
                [14, 'Mitisk\\Yii2Admin\\models\\File', 'storage_type', '', 'string', 'Хранение файлов', null, 1764424973],
                [15, 'Mitisk\\Yii2Admin\\models\\File', 's3_key', '', 'string', 'Access Key', 'Access Key ID', 1764424973],
                [16, 'Mitisk\\Yii2Admin\\models\\File', 's3_secret', '', 'string', 'Secret Key', 'Secret Access Key', 1764424973],
                [17, 'Mitisk\\Yii2Admin\\models\\File', 's3_region', '', 'string', 'Регион', 'Регион S3, например, ru-msk', 1764424973],
                [18, 'Mitisk\\Yii2Admin\\models\\File', 's3_bucket', '', 'string', 'Бакет', 'Имя S3-бакета', 1764424973],
                [19, 'Mitisk\\Yii2Admin\\models\\File', 's3_endpoint', '', 'string', 'Endpoint', 'Кастомный S3 endpoint (для S3-совместимых провайдеров)', 1764424973],
                [20, 'Mitisk\\Yii2Admin\\models\\File', 's3_path_style', '', 'boolean', 'Path-style адресация', 'Использовать path-style для endpoint', 1764424973],
                [21, 'Mitisk\\Yii2Admin\\models\\File', 's3_prefix', '', 'string', 'Префикс пути', 'Префикс ключей в бакете', 1764424973],
                [22, 'Mitisk\\Yii2Admin\\models\\File', 'ftp_host', '', 'string', 'Host', null, 1764424973],
                [23, 'Mitisk\\Yii2Admin\\models\\File', 'ftp_user', '', 'string', 'Логин', null, 1764424973],
                [24, 'Mitisk\\Yii2Admin\\models\\File', 'ftp_pass', '', 'string', 'Пароль', null, 1764424973],
                [25, 'Mitisk\\Yii2Admin\\models\\File', 'ftp_port', '', 'string', 'Порт', null, 1764424973],
                [26, 'Mitisk\\Yii2Admin\\models\\File', 'ftp_path', '', 'string', 'Path', null, 1764424973],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаление в обратном порядке из-за внешних ключей

        $this->dropTable('{{%settings}}');
        $this->dropTable('{{%settings_block}}');
        $this->dropTable('{{%menu}}');
        $this->dropTable('{{%file}}');
        $this->dropTable('{{%email_templates}}');

        // RBAC
        $this->dropTable('{{%auth_assignment}}');
        $this->dropTable('{{%auth_item_child}}');
        $this->dropTable('{{%auth_item}}');
        $this->dropTable('{{%auth_rule}}');

        $this->dropTable('{{%admin_widget_component}}');
        $this->dropTable('{{%admin_widget}}');
        $this->dropTable('{{%admin_note}}');
        $this->dropTable('{{%admin_model}}');
        $this->dropTable('{{%admin_controller_map}}');
        $this->dropTable('{{%user}}');
    }
}