<p align="center">
  <img src="assets/img/logo.png" alt="Logo" width="200" />
</p>

# Yii2 Admin Module

[![Latest Stable Version](https://poser.pugx.org/mitisk/yii2-admin/v/stable)](https://packagist.org/packages/mitisk/yii2-admin)
[![Total Downloads](https://poser.pugx.org/mitisk/yii2-admin/downloads)](https://packagist.org/packages/mitisk/yii2-admin)

Модуль административной панели для Yii2 приложений. Предоставляет готовый интерфейс для управления пользователями, настройками, меню и другими аспектами системы.

## 📋 Основные возможности

- **Управление пользователями**: CRUD операции, назначение ролей (RBAC), блокировка/активация.
- **Сброс пароля**: Функционал генерации и отправки нового пароля пользователю на email.
- **Имперсонация**: Возможность входа под другим пользователем ("Login As") для отладки.
- **Управление настройками**: Глобальное хранилище настроек (key-value) с удобным интерфейсом.
- **Email шаблоны**: Управление шаблонами писем с поддержкой плейсхолдеров.
- **SEO-правила**: Динамическое управление мета-тегами по URL-паттернам с поддержкой плейсхолдеров.
- **RBAC**: Для управления ролями и разрешениями.
- **Меню**: Динамическое управление пунктами меню.
- **Панель администратора на сайте**: плавающая панель с контекстом страницы, поиском и правкой текста на месте.

---

## ⚙️ Установка и Настройка

Предпочтительный способ установки — через [composer](http://getcomposer.org/download/).

Запустите:

```bash
composer create-project --prefer-dist yiisoft/yii2-app-basic .
composer require mitisk/yii2-admin
composer require aws/aws-sdk-php //Если планируется использовать S3
```

Отредактируйте db.php. Для создания таблиц в БД выполните команду:

```bash
php yii migrate --migrationPath=@vendor/mitisk/yii2-admin/migrations
```

> После применения миграций будет создан администратор по умолчанию:
>
> - **Login**: `admin`
> - **Password**: `123456`

### 1. Подключение модуля

Добавьте модуль в конфигурацию вашего приложения (`config/web.php` или `common/config/main.php`):

```php
'modules' => [
    'admin' => [
        'class' => 'Mitisk\Yii2Admin\Module',
        //'layout' => 'admin', // Используемый лейаут
    ],
    // ...
],
```

### 2. Настройка компонентов

#### Settings Component

Для работы с настройками зарегистрируйте компонент:

```php
'components' => [
    'settings' => [
        'class' => 'Mitisk\Yii2Admin\components\SettingsComponent',
    ],
    // ...
],
```

Использование в коде:

```php
// Сохранить настройку
Yii::$app->settings->set('Mitisk\Yii2Admin\models\Settings', 'api_key', 'your-key');

// Получить настройку
$apiKey = Yii::$app->settings->get('Mitisk\Yii2Admin\models\Settings', 'api_key');
```

#### Красивые URL

Пример конфигурации `urlManager`:

```php
'components' => [
    'urlManager' => [
        'enablePrettyUrl' => true,
        'showScriptName' => false,
        'suffix' => '/',
        'normalizer' => [
            'class' => 'yii\web\UrlNormalizer',
            'normalizeTrailingSlash' => true,
            'collapseSlashes' => true,
        ],
        'rules' => [
            '/' => 'site/index',
        ]
        //'rules' => require_once(__DIR__ . '\url_rules.php'),
    ],
    // ...
],
```

#### Форматирование

Пример конфигурации `formatter`:

```php
'components' => [
    'formatter' => [
        'class' => yii\i18n\Formatter::class,
        'locale' => 'ru-RU',
        'timeZone' => 'Europe/Moscow',
        'defaultTimeZone' => 'UTC',
        'dateFormat' => 'php:d MMMM Y',
        'timeFormat' => 'php:H:i:s',
        'datetimeFormat' => 'php:d MMMM Y H:i:s',
        'decimalSeparator' => ',',
        'thousandSeparator' => ' ',
        'currencyCode' => 'RUR',
    ],
    // ...
],
```

#### bootstrap

Пример конфигурации `bootstrap`:

```php
'bootstrap' => ['log', 'admin'],
```

---

## 🚀 Функционал

### Управление пользователями (`UserController`)

Контроллер предоставляет полный набор действий для администрирования пользователей:

- **Просмотр и поиск**: Фильтрация списка пользователей.
- **Создание и Редактирование**: Управление профилем, аватаром и статусом.
- **Управление ролями**: Назначение и отзыв ролей RBAC прямо в форме редактирования.
- **Отправка нового пароля**:
  - Доступно в форме редактирования пользователя.
  - Генерирует случайный пароль.
  - Отправляет письмо по шаблону `new_user_password`.
  - Требует наличия email и типа авторизации "Пароль" или "Пароль + код".
- **Вход под пользователем**: Действие `login-as` позволяет администратору авторизоваться под любым пользователем.

### Виджет меню (`MenuWidget`)

Для добавления пунктов меню в виджет используйте событие:

```php
use Mitisk\Yii2Admin\widgets\MenuWidget;

Yii::$app->on(MenuWidget::EVENT_BEFORE_RENDER, function ($event) {
    $event->menuArray[] = [
        'label' => 'Новый пункт',
        'href' => '/new-item',
        'icon' => 'icon-name'
    ];
});
```

### Email Шаблоны

Модуль использует систему шаблонов для отправки писем.

- **Модель**: `EmailTemplate`
- **Сервис**: `Mitisk\Yii2Admin\components\MailService`

Пример отправки письма:

```php
$mailService = new \Mitisk\Yii2Admin\components\MailService();
$mailService->send('template_slug', 'user@example.com', [
    'PARAM1' => 'Value 1',
    'PARAM2' => 'Value 2',
]);
```

### SEO-правила (`SeoRuleController`)

Модуль динамического управления SEO-мета-тегами. Правила привязываются к URL через регулярные выражения и автоматически применяются на сайте.

**Админка** — раздел доступен по адресу `/admin/seo-rule/`. Позволяет создавать, редактировать, удалять и переключать активность правил.

#### Настройка компонента

Зарегистрируйте компонент `seo` в `config/web.php`:

```php
'components' => [
    'seo' => [
        'class' => 'Mitisk\Yii2Admin\components\SeoManager',
    ],
    // ...
],
```

#### Вызов на сайте

В layout вашего приложения (или в `beforeAction` контроллера) зарегистрируйте мета-теги:

```php
// В layout (например, views/layouts/main.php), перед <!DOCTYPE html>:
Yii::$app->seo->register();
```

#### Передача динамических переменных

Из контроллера передайте контекстные данные для подстановки в шаблоны:

```php
// В экшене контроллера:
Yii::$app->seo->setContext([
    'category_name' => $category->name,
    'count' => $dataProvider->getTotalCount(),
    'brand' => $brand->title,
]);
```

В SEO-правиле используйте плейсхолдеры `{category_name}`, `{count}`, `{brand}`:

| Поле        | Пример значения                                              |
|-------------|--------------------------------------------------------------|
| URL паттерн | `/catalog/.*`                                                |
| Title       | `{category_name} — купить в интернет-магазине ({count} шт.)` |
| Description | `Большой выбор {category_name}. В наличии {count} товаров.`  |
| Robots      | `index, follow`                                              |

#### Поля SEO-правила

| Поле           | Описание                                                                                                       |
|----------------|----------------------------------------------------------------------------------------------------------------|
| URL паттерн    | Регулярное выражение для URL. Без разделителей оборачивается в `#...#iu`. С разделителями (`/`, `#`, `~`, `@`) — используется как есть. |
| Title          | Мета-тег `<title>`. Поддерживает плейсхолдеры.                                                                 |
| Description    | Мета-тег `description`. Поддерживает плейсхолдеры.                                                             |
| Keywords       | Мета-тег `keywords`. Поддерживает плейсхолдеры.                                                                |
| Robots         | Мета-тег `robots` (например: `index, follow`, `noindex, nofollow`).                                            |
| OG Title       | Open Graph `og:title`. Если пусто — фолбэк на Title.                                                          |
| OG Description | Open Graph `og:description`. Если пусто — фолбэк на Description.                                              |
| OG Image       | Open Graph `og:image`. Полный URL изображения.                                                                 |
| Приоритет      | Целое число. Чем выше — тем раньше проверяется правило. При совпадении нескольких паттернов применяется первый.  |
| Активно        | Включает/отключает правило без удаления.                                                                       |

#### Примеры URL-паттернов

```
/catalog/.*          — все страницы каталога
/news/\d+            — страница новости по ID
^/contacts$          — точное совпадение с /contacts
/blog/(?!rss).*      — все страницы блога, кроме RSS
#^/product/[\w-]+#   — страница товара (с явным разделителем)
```

---

## 🔒 Права доступа (Permissions)

Основные разрешения, используемые в модуле:

- `viewUsers` - Просмотр списка пользователей.
- `createUsers` - Создание пользователей.
- `updateUsers` - Редактирование пользователей.
- `deleteUsers` - Удаление пользователей.
- `manageUserRoles` - Управление ролями пользователей.
- `admin` - Доступ к админ-панели и функции имперсонации.

---

## 🧭 Панель администратора на сайте (Admin Bar)

Плавающая панель для залогиненного администратора поверх страниц сайта: переход в админку,
разделы, поиск по командам (`Ctrl+K`), контекст текущей записи («Редактировать»), быстрые
действия (например, очистка кэша) и правка текста прямо на странице. Для обычных посетителей
панель не выводится и не подключает ассеты.

Подключение — одна строка в лейауте сайта перед `</body>`:

```php
<?= \Mitisk\Yii2Admin\widgets\AdminBar::widget() ?>
```

Контекст страницы из контроллера сайта:

```php
Yii::$app->adminBar->setModel($product);                       // чип «Товары · Название» + кнопка «Редактировать»
Yii::$app->adminBar->addAction('export', 'Экспорт', ['url' => '/admin/reports/export/', 'icon' => 'download']);
Yii::$app->adminBar->addPanel('stats', 'Статистика', [['label' => 'Просмотров', 'value' => 128, 'icon' => 'eye']]);
```

Правка текста на странице (для посетителей выводится просто значение):

```php
<h1><?= \Mitisk\Yii2Admin\widgets\AdminBar::editable($product, 'name') ?></h1>
```

Сохранение идёт через `POST /admin/bar/attribute/` с проверкой права `{Model}\update`,
валидацией атрибута по `rules()` модели и записью в аудит-лог.

SEO-панель показывает, какое SEO-правило сработало для страницы и что оно выводит, или
предлагает создать правило под текущий URL. В меню аккаунта есть режимы «Смотреть как гость»
и «Показывать черновики». Черновики сайт подключает сам в своих выборках:

```php
$query = Product::find();
if (!Yii::$app->adminBar->showDrafts()) {      // для посетителя всегда false
    $query->andWhere(['status' => Product::STATUS_PUBLISHED]);
}
```

Настройки в «Панель администратора»: `bar_enabled`, `bar_mode` (`server` — панель рендерится
на сервере; `client` — страница одинакова для всех, панель подгружается скриптом и подходит для
полностраничного кэша), `bar_position` (`bottom` / `top`). Горячие клавиши: `Ctrl+K` — поиск,
`Alt+Shift+A` — свернуть/развернуть.

Расширение: серверные действия задаются в конфиге компонента `adminBar` (`serverActions`),
дополнительные панели и бейджи — обработчиком события `AdminBarState::EVENT_BUILD`.
Подробнее — в `docs/admin-bar.md` и `CLAUDE.md`.

---

## 📝 Текстовые блоки (раздел «Контент»)

Именованные фрагменты сайта — телефон, текст на главной, баннер, ссылка на оферту —
которые разработчик выводит одной строкой, а администратор правит в разделе «Контент» или прямо
на странице через Admin Bar. Типы: текст, HTML, картинка, ссылка.

```php
use Mitisk\Yii2Admin\widgets\ContentBlock;
use Mitisk\Yii2Admin\enums\BlockType;

<?= ContentBlock::widget(['key' => 'header.phone', 'name' => 'Телефон в шапке', 'default' => '+7 (495) 000-00-00']) ?>
<?= ContentBlock::widget(['key' => 'home.intro', 'type' => BlockType::Html, 'default' => '<p>Текст</p>']) ?>
<?= ContentBlock::widget(['key' => 'home.banner', 'type' => BlockType::Image, 'contentOptions' => ['class' => 'img-fluid']]) ?>
<?= ContentBlock::widget(['key' => 'footer.offer', 'type' => BlockType::Link, 'default' => ['text' => 'Оферта', 'url' => '/offer']]) ?>
```

Блока ещё нет в базе — он создаётся со значением `default` и сразу появляется в админке.
Удалённый блок, который всё ещё выводится в шаблоне, возвращается со значением из кода.
Значение без разметки: `Yii::$app->blocks->get('header.phone')`. Права: `viewContent`,
`editContent`, `manageContent`, роль «Контент-менеджер» (`contentManager`).

---

## 🔄 Обновление модуля

Обновление делается через composer, напрямую править `vendor/` не нужно.

### Из админки

В футере админки показывается бейдж с номером свежего релиза на GitHub. Для роли
`superAdminRole` он ведёт на страницу `/admin/default/update`, где есть проверка окружения
(composer, PHP CLI, права на `vendor/`), кнопка «Обновить сейчас» и живой лог. Кнопка запускает
фоновый процесс `php yii admin/update` — composer не выполняется в веб-запросе, поэтому
таймауты PHP-FPM не мешают.

Если на сервере нет прав на запись в `vendor/` от пользователя веб-сервера или отключён
`proc_open`, страница покажет, что именно мешает, и команды для ручного запуска.

### Из консоли или по cron

Подключите модуль в консольный конфиг (`config/console.php`) вместе с теми же компонентами
`settings`, `cache`, `authManager`, `db`, что и в web:

```php
'bootstrap' => ['log', 'admin'],
'modules' => [
    'admin' => ['class' => \Mitisk\Yii2Admin\Module::class],
],
```

Команды:

```bash
php yii admin/update          # composer update mitisk/yii2-admin + миграции + очистка кэша + запись версии
php yii admin/update/check    # показать, что найдено в окружении, без обновления
```

Ночное автообновление — одна строка в crontab владельца файлов проекта:

```
0 4 * * * /usr/bin/php /path/to/project/yii admin/update >> /path/to/project/runtime/admin-update.log 2>&1
```

Что делает `admin/update`: `composer update mitisk/yii2-admin --with-dependencies --optimize-autoloader`
(с `--no-dev`, если vendor был установлен без dev-зависимостей), затем в новом процессе применяет
миграции модуля, очищает кэш и сохраняет версию. Composer обновляет пакет только в пределах
ограничения из `composer.json` проекта (например `^1.5`); переход на новую мажорную версию — вручную.

Если composer или PHP CLI не находятся автоматически, укажите пути в
«Настройки → Основные» (`composer_path`, `php_path`).

---

## 🤖 Интеграция с Claude Code и другими AI-ассистентами

В корне пакета лежит файл [`CLAUDE.md`](CLAUDE.md) — подробная инструкция для AI-ассистента:
как подключить админку в проект, добавить сущность (компонент) миграцией, описать поля формы
и колонки таблицы в JSON, завести настройки, шаблоны писем, пункты меню, виджеты дашборда и
собственные контроллеры.

Чтобы Claude Code читал её в вашем проекте, добавьте в `CLAUDE.md` проекта строку:

```
@vendor/mitisk/yii2-admin/CLAUDE.md
```

---

## 📂 Структура

- `controllers/` - Контроллеры (User, Role, Settings, etc.)
- `models/` - Модели данных (AdminUser, Settings, EmailTemplate, etc.)
- `views/` - Представления админ-панели.
- `components/` - Служебные компоненты (MailService, SettingsComponent).
- `widgets/` - Виджеты интерфейса.
