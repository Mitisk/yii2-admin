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

## 📂 Структура

- `controllers/` - Контроллеры (User, Role, Settings, etc.)
- `models/` - Модели данных (AdminUser, Settings, EmailTemplate, etc.)
- `views/` - Представления админ-панели.
- `components/` - Служебные компоненты (MailService, SettingsComponent).
- `widgets/` - Виджеты интерфейса.
