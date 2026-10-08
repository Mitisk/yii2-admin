# Yii2 Admin (mitisk/yii2-admin) — инструкция для AI-ассистента

Этот файл описывает, как интегрировать админ-панель `mitisk/yii2-admin` в Yii2-проект
и как добавлять в неё сущности (компоненты), настройки, письма, пункты меню, виджеты
дашборда и собственные контроллеры. Он написан для Claude Code и других AI-ассистентов,
но пригоден и для разработчиков.

**Как подключить в проекте:** добавьте в `CLAUDE.md` вашего проекта строку
`@vendor/mitisk/yii2-admin/CLAUDE.md` (Claude Code импортирует файл), либо скопируйте
этот файл в `docs/` проекта. Версия модуля: см. `Mitisk\Yii2Admin\Module::VERSION`.

Все примеры ниже предполагают приложение `yii2-app-basic` с namespace `app\`.
Для advanced-шаблона замените `app\models` на `common\models`, `@app/views` на
`backend/views` и т.д.

---

## 1. Как устроена админка (прочитать обязательно)

- Модуль монтируется как `admin`, все URL начинаются с `/admin/`. Внутри модуля
  компонент `Yii::$app->user` подменяется на `adminUser` (identity —
  `Mitisk\Yii2Admin\models\AdminUser`, таблица `user`). Фронтовый `user` проекта
  не затрагивается, но таблица `user` общая.
- **Сущность в админке = запись в таблице `admin_model`** (AR-класс
  `Mitisk\Yii2Admin\models\AdminModel`, в UI называется «Компонент»). Запись хранит:
  название, alias (slug в URL), FQCN модели проекта, JSON-конфиг колонок списка (`list`),
  JSON-конфиг формы (`data`), ссылки-кнопки (`links`), подписи полей (`attribute_labels`).
- CRUD для всех сущностей обслуживает **один универсальный контроллер**
  `Mitisk\Yii2Admin\core\controllers\AdminController`. Правило
  `Mitisk\Yii2Admin\components\UrlRule` превращает `/admin/<alias>/<action>/` в
  `admin/core/<action>?model_class=<FQCN>`. Писать контроллеры/вьюхи для обычной
  сущности **не нужно** — нужна только AR-модель и запись в `admin_model`.
- Форма и таблица собираются из классов полей `Mitisk\Yii2Admin\fields\*Field`
  по типу, указанному в JSON `data`.
- Файлы хранятся полиморфно в таблице `file` (`class_name`, `item_id`, `field_name`),
  физически — локально, на FTP или в S3 (настройка в админке).
- Права: Yii RBAC (`yii\rbac\DbManager`). На каждую сущность — четыре разрешения
  `{FQCN}\view`, `{FQCN}\create`, `{FQCN}\update`, `{FQCN}\delete`
  (например `app\models\Product\view`). Роль `admin` (и `superAdminRole`) проходит
  везде без этих разрешений.
- Настройки: таблица `settings` (key-value по разделам) + фасад `Yii::$app->settings`.
- Письма: таблица `email_templates` + сервис `Mitisk\Yii2Admin\components\MailService`.
- Меню слева: таблица `menu`, запись с `alias = 'admin'`, JSON-массив пунктов.
- Все действия create/update/delete пишутся в аудит-лог автоматически.
- Доступ к редактору компонентов (`/admin/components/`) — только роль `superAdminRole`.
  Пользователь по умолчанию: `admin` / `123456` (создаётся миграцией модуля).

Полезно: любой компонент можно сначала настроить руками в UI
(`/admin/components/update?id=N`), а потом скопировать получившиеся JSON из
`admin_model.list` и `admin_model.data` в миграцию.

---

## 2. Подключение в проект

```bash
composer require mitisk/yii2-admin
composer require aws/aws-sdk-php      # только если нужен S3
php yii migrate --migrationPath=@vendor/mitisk/yii2-admin/migrations --interactive=0
```

### 2.1 Web-конфиг (`config/web.php`)

```php
'bootstrap' => ['log', 'admin'],          // ОБЯЗАТЕЛЬНО: модуль регистрирует URL-правила в bootstrap()
'modules' => [
    'admin' => ['class' => \Mitisk\Yii2Admin\Module::class],
],
'components' => [
    // ОБЯЗАТЕЛЬНО: модуль читает версию, логотип, SMTP и т.п. через этот компонент
    'settings' => ['class' => \Mitisk\Yii2Admin\components\SettingsComponent::class],
    // ОБЯЗАТЕЛЬНО: authManager использует кэш с id 'cache'
    'cache' => ['class' => \yii\caching\FileCache::class],
    // Модуль создаст authManager сам, если его нет, но лучше задать явно (нужен и в console)
    'authManager' => [
        'class' => \yii\rbac\DbManager::class,
        'defaultRoles' => ['guest', 'user'],
        'cache' => 'cache',
        'cacheKey' => 'rbac',
    ],
    // ОБЯЗАТЕЛЬНО: pretty URL
    'urlManager' => [
        'enablePrettyUrl' => true,
        'showScriptName' => false,
        'suffix' => '/',
        'normalizer' => [
            'class' => \yii\web\UrlNormalizer::class,
            'normalizeTrailingSlash' => true,
            'collapseSlashes' => true,
        ],
        'rules' => ['/' => 'site/index'],
    ],
    // Опционально: SEO-правила из админки
    'seo' => ['class' => \Mitisk\Yii2Admin\components\SeoManager::class],
],
```

`mailer` приложения не нужен: `MailService` строит свой SMTP-транспорт из настроек в БД.

### 2.2 Console-конфиг (`config/console.php`)

Миграции проекта, которые регистрируют компоненты через AR `AdminModel`, вызывают
`Yii::$app->authManager` (создание разрешений) и могут обращаться к `Yii::$app->settings`.
Поэтому в консольном конфиге должны быть **те же** `settings`, `cache`, `authManager`
(вынесите их в общий файл и подключайте в оба конфига). Если `authManager` в консоли
отсутствует, разрешения для сущности **молча не создадутся** и пункт меню будет
скрыт для всех ролей, кроме супер-админа.

### 2.3 Проверка

1. `/admin/login` → `admin` / `123456`.
2. При смене версии пакета админка сама редиректит на `/admin/default/upgrade`,
   где можно применить миграции модуля из UI.
3. `/admin/components/` — список компонентов (нужна роль `superAdminRole`).

---

## 3. Добавление сущности (компонента) — основной сценарий

### 3.1 Порядок действий

1. Миграция: создать таблицу. **Первичный ключ обязан называться `id`** (ссылки,
   файлы и связи завязаны на `$model->id`).
2. AR-модель в проекте (`app\models\Product`) — см. требования в 3.2.
3. Миграция (или консольная команда): зарегистрировать компонент — создать/обновить
   запись `admin_model` через AR `Mitisk\Yii2Admin\models\AdminModel` (см. 3.9).
   Сохранение через AR автоматически добавляет пункт меню и создаёт RBAC-разрешения
   (только при `in_menu = 1`). Прямой `$this->insert('{{%admin_model}}', ...)` этого
   не делает.
4. Применить миграции, открыть `/admin/<alias>/`.

### 3.2 Требования к AR-модели

- Наследует `yii\db\ActiveRecord`, таблица с PK `id`.
- **Каждый атрибут, который редактируется в форме, должен быть в `rules()`** —
  админка делает `$model->load(post)` + `save()`; атрибуты вне правил молча
  игнорируются. Валидация модели — единственная серверная валидация.
- `attributeLabels()` — подписи по умолчанию для колонок и полей (их можно
  переопределить в `admin_model.attribute_labels` и в `label` поля холста).
- Для select со статическим списком: **public static** метод без обязательных
  параметров, возвращающий `[значение => подпись]`, например `getStatusList()`.
- Для select по связи: метод связи с **объявленным типом возврата**
  `\yii\db\ActiveQuery` (без `?`), например `getCategory(): \yii\db\ActiveQuery`.
  Без типа возврата метод не появится в UI-редакторе, хотя из JSON работать будет.
- Для множественного выбора (many-to-many) и для файлов без колонки в таблице —
  **публичное свойство** модели + правило `safe`. Имя свойства **не должно совпадать
  с именем связи** (`public $tag_ids` + `getTags()`, а не `public $tags`): свойство
  перекроет магический доступ к связи, и таблица/просмотр покажут пустоту.
- `TimestampBehavior` для `created_at`/`updated_at` (int) — рекомендуется;
  поля дат храните как unix timestamp (int) — так работает фильтр по датам.

Пример модели:

```php
<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $category_id
 * @property int $status
 * @property float|null $price
 * @property string|null $description
 * @property int $published
 * @property int|null $published_at
 * @property int|null $author_id
 * @property int $created_at
 * @property int $updated_at
 */
class Product extends ActiveRecord
{
    public const STATUS_DRAFT = 0;
    public const STATUS_ACTIVE = 1;

    /** @var int[] виртуальный атрибут для multi-select по связи getTags() */
    public $tag_ids = [];

    /** @var mixed виртуальный атрибут для файлов (колонки в таблице нет) */
    public $images;

    public static function tableName(): string
    {
        return '{{%product}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name', 'slug'], 'required'],
            [['name', 'slug'], 'string', 'max' => 255],
            [['slug'], 'unique'],
            [['category_id', 'status', 'published', 'published_at', 'author_id'], 'integer'],
            [['price'], 'number'],
            [['description'], 'string'],
            [['tag_ids', 'images'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Название',
            'slug' => 'URL',
            'category_id' => 'Категория',
            'status' => 'Статус',
            'price' => 'Цена',
            'description' => 'Описание',
            'published' => 'Опубликован',
            'published_at' => 'Дата публикации',
            'author_id' => 'Автор',
        ];
    }

    /** Источник значений для select (тип источника "method"). */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_ACTIVE => 'Активен',
        ];
    }

    /** hasOne — одиночный select по связи, значение пишется в category_id. */
    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    /** many-to-many через viaTable — множественный select. */
    public function getTags(): ActiveQuery
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('{{%product_tag}}', ['product_id' => 'id']);
    }
}
```

### 3.3 Поля записи `admin_model`

| Поле | Назначение |
|---|---|
| `name` | Название компонента (заголовок раздела, пункт меню). |
| `alias` | Slug в URL: `/admin/<alias>/`. Только `a-z0-9-`, кириллица транслитерируется автоматически. Уникален. Не должен совпадать с зарезервированными (см. §11). |
| `table_name` | Имя таблицы **без префикса**. Уникальный индекс. |
| `model_class` | FQCN AR-модели (`app\models\Product`). |
| `admin_label` | Атрибут-заголовок записи (хлебные крошки, подписи в связях, аудит). Если пусто — `name`, затем `title`. |
| `view` | 1 — компонент активен и виден. 0 — скрыт (так «удаляют» компонент). |
| `in_menu` | 1 — при сохранении через AR добавится пункт в меню и создадутся разрешения для роли `manager`. |
| `can_create` | 1 — показывать кнопку «Добавить». |
| `non_encode` | 1 — не экранировать HTML в ячейках/просмотре. |
| `default_sort_attribute`, `default_sort_direction` | Сортировка списка по умолчанию. Направление: `SORT_ASC` (4) / `SORT_DESC` (3). Пусто — по PK. |
| `attribute_labels` | JSON `{"attr": "Подпись"}` — переопределяет `attributeLabels()` модели. |
| `file_path` | Серверный путь для файлов именно этого компонента, напр. `/web/uploads/products/`. Если задан — файлы хранятся локально по этому пути, минуя глобальное хранилище. |
| `list` | JSON-конфиг колонок таблицы (§3.4). **Если пусто — таблица будет без колонок.** |
| `data` | JSON-конфиг формы/холста (§3.5). **Если пусто — форма пустая.** |
| `links` | JSON-пул ссылок-кнопок (§3.8). |

Важно: открытие `/admin/components/` автоматически создаёт записи `admin_model`
(`view = 1`, `name = table_name`, без alias и класса) для **всех** таблиц БД, которых
там ещё нет. Поэтому в миграции всегда ищите существующую запись по `table_name`
и обновляйте её, а не вставляйте новую (иначе — нарушение уникального индекса).

### 3.4 Формат `list` (колонки таблицы)

Объект, ключ — имя колонки, порядок ключей = порядок колонок. Показываются только
колонки с `"on": "1"`.

```json
{
  "admin_checkbox": {"on": "1"},
  "admin_number":   {"on": "1"},
  "name":           {"on": "1"},
  "category_id":    {"on": "1"},
  "price":          {"on": "1"},
  "published_at":   {"on": "1"},
  "published":      {"on": "1"},
  "author_id":      {"on": "1"},
  "admin_link_1":   {"on": "1", "type": "links", "name": "Ссылки", "items": ["lnk_site"]},
  "admin_actions":  {"on": "1", "data": {"view": "1", "update": "1", "delete": "1"}}
}
```

- `admin_checkbox` — чекбоксы для массового удаления; `admin_number` — порядковый №.
- `admin_actions` — кнопки действий. Если ключ отсутствует — доступны все действия.
  Если ключ есть, но `on` пустой — действия (и права view/update/delete на уровне
  компонента) отключены полностью.
- `admin_link_*` — слот ссылок-кнопок из пула `links`.
- Рендер колонки (бейджи для select, превью файлов, тумблер для чекбокса, аватар
  для пользователя, формат даты, фильтры) берётся из описания одноимённого поля
  в `data`. Колонка, которой нет в `data`, выводится как обычный атрибут.
- Общий поиск ищет `LIKE` по всем атрибутам модели; фильтры по колонкам включаются
  кнопкой в шапке списка.

### 3.5 Формат `data` (визуальный холст формы)

Массив элементов в порядке вывода. Элемент либо поле модели, либо контент-блок.

Поле модели (все ключи, кроме `name`, `type`, необязательны):

```json
{
  "name": "category_id",
  "label": "Категория",
  "type": "select",
  "width": "50",
  "required": true,
  "readonly": false,
  "hint": "Подсказка под полем",
  "roles": ["admin", "manager"],
  "withTime": false,
  "fileMultiple": false,
  "selectMultiple": false,
  "selectSourceType": "entity",
  "selectSourceVal": "getCategory",
  "selectSaveMethod": ""
}
```

- `width`: `"100"`, `"50"`, `"33"`, `"25"` (доля строки формы).
- `roles`: массив имён ролей/разрешений; поле видно, если у пользователя есть хотя бы
  одно (`@` — любой авторизованный). Пусто — видно всем.
- `required` и `readonly` — только HTML-атрибуты; серверная валидация — `rules()` модели.
- Не добавляйте ключи, которых нет в этой схеме: неизвестный ключ передаётся
  в конструктор класса поля и вызовет исключение «Setting unknown property».

Контент-блоки (`isContent: true`):

```json
{"isContent": true, "type": "header",    "tag": "h3", "text": "Основное", "width": "100"}
{"isContent": true, "type": "paragraph", "text": "Пояснение для редактора", "width": "100"}
{"isContent": true, "type": "divider"}
{"isContent": true, "type": "link", "link_id": "lnk_site", "label": "Открыть на сайте", "width": "25"}
```

Типы полей (`type`):

| type | Что рендерит | Колонка в БД | Примечания |
|---|---|---|---|
| `text` | `<input type=text>` | string | В списке `strip_tags`. |
| `textarea` | textarea | text | |
| `html` | textarea + Ace (HTML-код) | text | Сохраняется как есть. Для вывода HTML в просмотре нужен `non_encode = 1`. |
| `visual` | WYSIWYG Trumbowyg | text | То же. |
| `select` | Tom Select | int/string или виртуальный атрибут | См. §3.6. |
| `file` | FileUploader (превью, alt, lightbox) | int/string или виртуальный атрибут | См. §3.7. |
| `date` | date / datetime-local (`withTime`) | int (timestamp) или datetime/string | Int-колонка получает unix timestamp; строковая — `Y-m-d` или `Y-m-dTH:i:s`. Фильтр в списке: `=`, `≥`, `≤`. |
| `posted` | чекбокс 1/0; в списке — тумблер с AJAX-переключением | tinyint | Для колонок `active`, `published`, `status` подписи «Активно/Неактивно». |
| `number` | `<input type=number>` | int/decimal | |
| `hidden` | скрытый input | любая | Без `value` в JSON отправляет пустое значение; для константы добавьте `"value": "..."`. |
| `user` | автокомплит по таблице `user`, в списке аватар + имя + роли | int (id пользователя) | Поиск в фильтре по логину/имени/email. |

Автоопределение типа по имени колонки в UI-редакторе: `created_at|updated_at|date|datetime` → date,
`text|data|json|html` → textarea, `file|image|files|images` → file, `published|active` → posted,
`user_id|author_id|created_by|updated_by|owner_id` → user, остальное → text.

### 3.6 Select: три сценария

1. **Статический список** — `selectSourceType: "method"`, `selectSourceVal: "getStatusList"`,
   `name` = колонка (`status`). Значение сохраняется обычным `load()`.
2. **Связь hasOne (внешний ключ)** — `selectSourceType: "entity"`, `selectSourceVal: "getCategory"`,
   `name` = колонка FK (`category_id`), `selectSaveMethod` пустой. Значение сохраняется
   обычным `load()`. Варианты — все записи связанной модели; подпись берётся из
   `admin_label` компонента связанной модели, иначе из `name|title|label|key|username|email|slug`.
   В списке — цветной бейдж со ссылкой на карточку связанной записи (если у неё есть компонент).
3. **Many-to-many** — `selectMultiple: true`, `selectSourceType: "entity"`,
   `selectSourceVal: "getTags"`, `selectSaveMethod: "getTags"` (можно опустить —
   подставится источник), `name` = **виртуальное свойство** (`tag_ids`), не совпадающее
   с именем связи. Связь — `hasMany(...)->viaTable(...)`. При сохранении админка делает
   `unlinkAll` + `link` по junction-таблице; при удалении записи — `unlinkAll`.
   Junction-таблица должна существовать (`product_tag(product_id, tag_id)`).

### 3.7 Файлы

- `name` поля — либо колонка таблицы, либо виртуальное свойство. Если колонка есть,
  после загрузки в неё записывается **id записи `file`** (последнего файла) — делайте
  колонку `int` или `string`, не `required`.
- Файлы хранятся в таблице `file`: `class_name = FQCN модели`, `item_id = id записи`,
  `field_name = имя поля`. Получить на фронте:

```php
use Mitisk\Yii2Admin\fields\FieldsHelper;

/** @var \Mitisk\Yii2Admin\models\File[] $files */
$files = FieldsHelper::getFiles($product, 'images');
foreach ($files as $file) {
    $url = $file->getUrl();        // публичный URL (local / S3 / FTP)
    $alt = $file->alt_attribute;
    $isImage = $file->isImage();
}
```

- Хранилище по умолчанию выбирается в админке (`Настройки → Файлы`): `local`
  (папка `uploads/` относительно текущей директории, путь в БД вида
  `/web/uploads/<name>`), `s3`, `ftp`. Если у компонента задан `file_path`
  (например `/web/uploads/products/`), файлы кладутся в `@app/web/uploads/products/`
  и отдаются как `/web/uploads/products/<name>`.
- **Проверьте, что путь `/web/...` доступен по HTTP** (document root — корень проекта
  или есть соответствующий rewrite/alias): URL строятся именно так.
- Старый формат «путь к файлу прямо в колонке» (`image = 'pic.jpg'`) тоже
  поддерживается на чтение: URL = `file_path` + значение.

### 3.8 Ссылки-кнопки (`links`)

Переиспользуемые кнопки с подстановкой атрибутов записи в URL: `{id}`, `{slug}`, любой атрибут.

```json
[
  {"id": "lnk_site", "title": "На сайте", "icon": "icon-external-link",
   "color": "pastel-blue", "url": "/product/{slug}/", "target": "_blank"},
  {"id": "lnk_recalc", "title": "Пересчитать", "icon": "icon-refresh-ccw",
   "color": "pastel-green", "url": "/admin/reports/recalc/?id={id}", "target": "ajax"}
]
```

- `target`: `_self`, `_blank`, `ajax` (POST с CSRF на `url` через fetch; кнопка подсвечивается
  зелёным/красным по HTTP-статусу ответа, тело ответа не показывается).
- `icon` — только из белого списка `Mitisk\Yii2Admin\core\components\LinkPalette::icons()`
  (`icon-eye`, `icon-edit-3`, `icon-trash-2`, `icon-plus`, `icon-search`, `icon-settings`,
  `icon-info`, `icon-copy`, `icon-link`, `icon-external-link`, `icon-download`,
  `icon-upload`, `icon-mail`, `icon-file`, `icon-folder`, `icon-calendar`, `icon-clock`,
  `icon-check`, `icon-x`, `icon-star`, `icon-user`, `icon-users`, `icon-lock`,
  `icon-refresh-ccw`, `icon-printer`, `icon-arrow-right`, `icon-home`, `icon-filter`,
  `icon-play` и др.). Неизвестная иконка/цвет молча отбрасываются.
- `color`: `pastel-blue|green|pink|yellow|purple|orange|cyan|red|gray`.
- Использование: в `list` через слот `admin_link_*` (`items: ["lnk_site"]`), в `data`
  через контент-блок `type: "link"`.

### 3.9 Полный пример миграции регистрации компонента

```php
<?php

use yii\db\Migration;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\AdminModelInfo;
use Mitisk\Yii2Admin\models\Menu;

/**
 * Регистрирует сущность app\models\Product в админке.
 * Таблица product должна быть создана предыдущей миграцией.
 */
class m260101_000100_register_product_admin_component extends Migration
{
    private const TABLE = 'product';
    private const ALIAS = 'product';
    private const MODEL = \app\models\Product::class;

    public function safeUp(): void
    {
        // /admin/components/ мог уже создать «пустую» запись для таблицы — обновляем её.
        $component = AdminModel::findOne(['table_name' => self::TABLE]) ?? new AdminModel();

        $component->setAttributes([
            'name'        => 'Товары',
            'alias'       => self::ALIAS,
            'table_name'  => self::TABLE,
            'model_class' => self::MODEL,
            'admin_label' => 'name',
            'view'        => 1,
            'in_menu'     => 1,   // пункт меню + разрешения для роли manager (см. afterSave)
            'can_create'  => 1,
            'non_encode'  => 0,
            'file_path'   => '/web/uploads/products/',
            'default_sort_attribute' => 'id',
            'default_sort_direction' => SORT_DESC,
            'attribute_labels' => ['slug' => 'URL (slug)'],
            'links' => [
                ['id' => 'lnk_site', 'title' => 'На сайте', 'icon' => 'icon-external-link',
                 'color' => 'pastel-blue', 'url' => '/product/{slug}/', 'target' => '_blank'],
            ],
            'list' => [
                'admin_checkbox' => ['on' => '1'],
                'admin_number'   => ['on' => '1'],
                'name'           => ['on' => '1'],
                'category_id'    => ['on' => '1'],
                'status'         => ['on' => '1'],
                'price'          => ['on' => '1'],
                'published_at'   => ['on' => '1'],
                'published'      => ['on' => '1'],
                'author_id'      => ['on' => '1'],
                'admin_link_1'   => ['on' => '1', 'type' => 'links', 'name' => '', 'items' => ['lnk_site']],
                'admin_actions'  => ['on' => '1', 'data' => ['view' => '1', 'update' => '1', 'delete' => '1']],
            ],
            // data — строка JSON
            'data' => json_encode([
                ['isContent' => true, 'type' => 'header', 'tag' => 'h3', 'text' => 'Основное'],
                ['name' => 'name', 'label' => 'Название', 'type' => 'text', 'required' => true, 'width' => '50'],
                ['name' => 'slug', 'label' => 'URL', 'type' => 'text', 'required' => true, 'width' => '50',
                 'hint' => 'Латиница и дефисы'],
                ['name' => 'category_id', 'label' => 'Категория', 'type' => 'select', 'width' => '50',
                 'selectSourceType' => 'entity', 'selectSourceVal' => 'getCategory'],
                ['name' => 'status', 'label' => 'Статус', 'type' => 'select', 'width' => '50',
                 'selectSourceType' => 'method', 'selectSourceVal' => 'getStatusList'],
                ['name' => 'tag_ids', 'label' => 'Теги', 'type' => 'select', 'selectMultiple' => true,
                 'selectSourceType' => 'entity', 'selectSourceVal' => 'getTags', 'selectSaveMethod' => 'getTags'],
                ['name' => 'price', 'label' => 'Цена', 'type' => 'number', 'width' => '33'],
                ['name' => 'published_at', 'label' => 'Дата публикации', 'type' => 'date', 'withTime' => true, 'width' => '33'],
                ['name' => 'published', 'label' => 'Опубликован', 'type' => 'posted', 'width' => '33'],
                ['isContent' => true, 'type' => 'divider'],
                ['name' => 'description', 'label' => 'Описание', 'type' => 'visual'],
                ['name' => 'images', 'label' => 'Изображения', 'type' => 'file', 'fileMultiple' => true],
                ['name' => 'author_id', 'label' => 'Автор', 'type' => 'user', 'roles' => ['admin']],
                ['isContent' => true, 'type' => 'link', 'link_id' => 'lnk_site', 'label' => 'На сайте', 'width' => '25'],
            ], JSON_UNESCAPED_UNICODE),
        ], false);

        if (!$component->save()) {
            throw new \RuntimeException('AdminModel: ' . print_r($component->getErrors(), true));
        }

        // Инструкция для контент-менеджеров (кнопка «i» над списком) — опционально
        $info = AdminModelInfo::findOne(['model_class' => self::MODEL]) ?? new AdminModelInfo(['model_class' => self::MODEL]);
        $info->content = '<p>Slug должен быть уникальным. Картинки — не более 5 штук.</p>';
        $info->save();

        // Разрешения для дополнительных ролей (manager получил их автоматически)
        $this->grantModelPermissions(self::MODEL, 'moderator', ['view', 'update']);
    }

    public function safeDown(): void
    {
        Menu::removeFromMenu('admin', '/admin/' . self::ALIAS . '/');
        AdminModelInfo::deleteAll(['model_class' => self::MODEL]);
        AdminModel::deleteAll(['table_name' => self::TABLE]);

        $auth = Yii::$app->authManager;
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            if ($p = $auth->getPermission(self::MODEL . '\\' . $action)) {
                $auth->remove($p);
            }
        }
    }

    private function grantModelPermissions(string $modelClass, string $roleName, array $actions): void
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);
        if (!$role) {
            return;
        }
        $titles = ['view' => 'Просмотр', 'create' => 'Создание', 'update' => 'Изменение', 'delete' => 'Удаление'];
        foreach ($actions as $action) {
            $name = $modelClass . '\\' . $action;
            $permission = $auth->getPermission($name);
            if (!$permission) {
                $permission = $auth->createPermission($name);
                $permission->description = $titles[$action] . ': ' . \yii\helpers\StringHelper::basename($modelClass);
                $auth->add($permission);
            }
            if (!$auth->hasChild($role, $permission)) {
                $auth->addChild($role, $permission);
            }
        }
    }
}
```

Если нужно обновить уже существующий компонент (добавили колонку) — та же миграция:
`findOne(['table_name' => ...])`, правка `list`/`data`, `save()`.

### 3.10 Права и меню для сущности

- Проверки в универсальном контроллере: `index/view` → `{FQCN}\view`,
  `create` → `{FQCN}\create`, `update` + AJAX-тумблер → `{FQCN}\update`,
  `delete` + массовое удаление → `{FQCN}\delete`. Либо роль `admin`.
- `AdminModel::afterSave()` при `in_menu = 1` и непустом `alias`:
  добавляет пункт `['text' => name, 'href' => '/admin/<alias>/', 'icon' => 'far fa-circle',
  'rule' => '{FQCN}\view']` **в корень** меню (идемпотентно по `href`) и создаёт
  четыре разрешения, привязывая их к роли `manager`.
- Хотите пункт во вложенном подменю — держите `in_menu = 0` и добавьте пункт
  вручную (§4); иначе при следующем сохранении компонента появится дубликат в корне.
- Супер-админ видит пункт через цепочку `superAdminRole → admin → manager → {FQCN}\view`.
- Разрешения для других ролей — через `/admin/role/` или `authManager` (см. пример выше).

### 3.11 Чек-лист после добавления

1. `/admin/<alias>/` открывается, колонки на месте, поиск и фильтры работают.
2. «Добавить» → форма со всеми полями, запись сохраняется (ошибки `rules()` выводятся во flash).
3. Карточка `view` показывает поля; `delete` удаляет файлы и связи.
4. Пункт меню виден нужным ролям.
5. В UI-редакторе (`/admin/components/`) компонент открывается без ошибок.

---

## 4. Меню слева

Хранится в `menu.data` (запись `alias = 'admin'`) как JSON-массив:

```json
[
  {"text": "Главная", "href": "/admin/", "icon": "fas fa-home", "target": "_self", "rule": "accessAdmin", "title": ""},
  {"text": "Каталог", "href": "#", "icon": "fas fa-box", "target": "_self", "rule": "app\\models\\Product\\view", "title": "",
   "children": [
     {"text": "Товары",    "href": "/admin/product/",  "icon": "empty", "target": "_self", "rule": "app\\models\\Product\\view",  "title": ""},
     {"text": "Категории", "href": "/admin/category/", "icon": "empty", "target": "_self", "rule": "app\\models\\Category\\view", "title": ""}
   ]}
]
```

- `icon` — классы Font Awesome 5 (`fas fa-…`, `far fa-…`); `empty` — без иконки (для детей).
- `rule` — имя разрешения или роли; пункт скрывается, если `Yii::$app->user->can(rule)` ложно.
  Пустое — виден всем. Если у родителя нет видимых детей, он всё равно показывается —
  задавайте `rule` и родителю.
- Активный пункт вычисляется по префиксу URL автоматически.

Добавить/удалить пункт в корне (идемпотентно по `href`):

```php
use Mitisk\Yii2Admin\models\Menu;

Menu::addToMenu('admin', [
    'text' => 'Отчёты', 'href' => '/admin/reports/', 'icon' => 'fas fa-chart-bar',
    'target' => '_self', 'rule' => 'viewReports', 'title' => '',
]);
Menu::removeFromMenu('admin', '/admin/reports/');
```

Вложенный пункт — правкой JSON:

```php
$menu = Menu::findOne(['alias' => 'admin']);
$items = json_decode($menu->data, true) ?: [];
$items[] = [ /* родитель с children, как выше */ ];
$menu->data = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
$menu->save(false);
```

Редактор меню в UI: `/admin/menu/`. Сохранение меню синхронизирует флаг
`admin_model.in_menu` по корневым `href` вида `/admin/<alias>/`.

Примечание: в README описано событие `MenuWidget::EVENT_BEFORE_RENDER` для
динамических пунктов. В текущей версии изменения `$event->menuArray` из обработчика
не попадают в рендер, а `Yii::$app->on(...)` не ловит событие виджета. Используйте
хранение пунктов в БД, как выше.

---

## 5. Настройки (константы сайта)

Таблица `settings`: `model_name` (ключ раздела), `attribute`, `value` (всегда строка),
`type`, `label`, `description`. Уникальный индекс `(model_name, attribute)`.
Таблица `settings_block`: `model_name`, `label`, `description` — заголовок/описание вкладки.

Разделы (вкладки на `/admin/settings/`):

| Ключ `model_name` | Где показывается |
|---|---|
| `GENERAL` | Вкладка «Основные» (`site_name`, `admin_email`, `timezone`, `api_key`, служебный `version`). |
| `ADMIN` | Вкладка «Панель администратора» (`logo`, тип `file`). |
| `HIDDEN` | Не показывается (например `mail_layout`). |
| `Mitisk\Yii2Admin\models\File` | Вкладка «Файлы» (storage_type, s3_*, ftp_*). |
| `Mitisk\Yii2Admin\models\MailTemplate` | SMTP: `mailserver_host`, `mailserver_port`, `mailserver_login`, `mailserver_password`, `mailserver_from_name`. |
| `Mitisk\Yii2Admin\models\AdminUser` | Выбор шаблонов писем пользователей (`mail_template_new_password` и др.). |
| FQCN модели проекта (`app\models\Product`) | Отдельная вкладка с названием компонента **и** кнопка-шестерёнка над списком этого компонента (для роли `admin`). |
| Любой свой ключ (`SHOP`) | Отдельная вкладка; заголовок берётся из `settings_block`. |

Зарезервированы: `GENERAL`, `ADMIN`, `HIDDEN`, `Mitisk\Yii2Admin\models\File`.

Типы (`type`) и приведение при чтении: `string` (по умолчанию), `text`/`textarea`
(textarea), `integer` → int, `float` → float, `boolean` → bool (`'1'|'true'|'on'`),
`json` → array (`json_decode`), `mail_template` → выпадающий список slug'ов шаблонов
писем, `file` → URL файла (используйте только для `ADMIN.logo`; в своих разделах
рендерится как текст).

Чтение/запись в коде:

```php
$currency = Yii::$app->settings->get('SHOP', 'currency', 'RUB');       // с дефолтом
$enabled  = Yii::$app->settings->get('SHOP', 'orders_enabled', false);  // bool
$limits   = Yii::$app->settings->get('SHOP', 'limits', []);             // json → array

Yii::$app->settings->set('SHOP', 'currency', 'USD');                     // type по умолчанию 'string'
Yii::$app->settings->set('SHOP', 'limits', json_encode($arr), 'json');  // значение приводится к строке
```

`set()` создаёт запись, если её нет, но **не** задаёт `label`/`description`. Для
настроек, которые админ будет видеть и редактировать, создавайте их миграцией:

```php
use Mitisk\Yii2Admin\models\Settings;
use Mitisk\Yii2Admin\models\SettingsBlock;

$block = SettingsBlock::findOne(['model_name' => 'SHOP']) ?? new SettingsBlock(['model_name' => 'SHOP']);
$block->label = 'Магазин';
$block->description = 'Параметры оформления заказа';
$block->save();

$rows = [
    // attribute, value, type, label, description
    ['currency',            'RUB',       'string',        'Валюта',                  'Код ISO 4217'],
    ['min_order',           '1000',      'integer',       'Минимальная сумма заказа', null],
    ['orders_enabled',      '1',         'boolean',       'Приём заказов',           null],
    ['order_notify_emails', '',          'text',          'Email для уведомлений',   'Через запятую'],
    ['mail_template_order', 'new_order', 'mail_template', 'Шаблон письма о заказе',  null],
];
foreach ($rows as [$attribute, $value, $type, $label, $description]) {
    $s = Settings::findOne(['model_name' => 'SHOP', 'attribute' => $attribute])
        ?? new Settings(['model_name' => 'SHOP', 'attribute' => $attribute]);
    $s->value = $value;
    $s->type = $type;
    $s->label = $label;
    $s->description = $description;
    $s->updated_at = time();
    $s->save();
}
```

Нюансы:
- Кэш настроек — in-memory на время запроса; `set()` его сбрасывает. Внешнего кэша нет.
- `attribute` — `^[A-Za-z_][A-Za-z0-9_]*$`.
- Настройки по FQCN модели удобно использовать для параметров конкретной сущности
  (`Yii::$app->settings->get(Product::class, 'per_page', 20)`).
- Интерфейс `Mitisk\Yii2Admin\components\PermissionConst` — легаси, в модуле не используется.

---

## 6. Письма

Таблица `email_templates`: `slug` (`^[a-z0-9_-]+$`, уникален), `name`, `subject`, `body`
(HTML с плейсхолдерами `{{VAR}}`), `params` (JSON с описанием переменных), `active`.
UI: `/admin/email-template/` (роли `superAdminRole`, `admin`), там же тестовая
отправка и общий макет письма.

Регистрация шаблона миграцией:

```php
use Mitisk\Yii2Admin\models\EmailTemplate;

$tpl = EmailTemplate::findOne(['slug' => 'new_order']) ?? new EmailTemplate(['slug' => 'new_order']);
$tpl->name    = 'Новый заказ';
$tpl->subject = 'Заказ №{{ORDER_ID}} принят';
$tpl->body    = '<p>Здравствуйте, {{NAME}}!</p><p>Ваш заказ №{{ORDER_ID}} на сумму {{TOTAL}} принят.</p>';
$tpl->params  = [
    'ORDER_ID' => ['desc' => 'Номер заказа', 'required' => '1'],
    'NAME'     => ['desc' => 'Имя клиента',  'required' => '0'],
    'TOTAL'    => ['desc' => 'Сумма заказа', 'required' => '1'],
];
$tpl->active  = 1;
$tpl->save();
```

Отправка:

```php
$mail = Yii::createObject(\Mitisk\Yii2Admin\components\MailService::class);
$ok = $mail->send('new_order', $order->email, [
    'ORDER_ID' => $order->id,
    'NAME'     => $order->name,
    'TOTAL'    => Yii::$app->formatter->asCurrency($order->total),
]);
```

Как работает `send()`:
- Возвращает `false` (и пишет в лог), если шаблон не найден/неактивен, не передан
  `required`-параметр или SMTP не настроен; исключений наружу не бросает.
- Плейсхолдеры заменяются в `subject` и `body`; непереданные переменные из `params`
  заменяются пустой строкой. Значения **не экранируются**.
- Тело оборачивается в макет из `HIDDEN.mail_layout` (должен содержать `{{content}}`),
  редактируется на `/admin/email-template/layout`.
- SMTP берётся из раздела настроек `Mitisk\Yii2Admin\models\MailTemplate`
  (`smtps` для порта 465, иначе `smtp`); `From` = логин + `mailserver_from_name`.
  `Yii::$app->mailer` не используется.

Рекомендуемый паттерн «событие → настраиваемый шаблон»: заведите настройку типа
`mail_template` (§5), чтобы админ выбирал шаблон сам:

```php
$slug = Yii::$app->settings->get('SHOP', 'mail_template_order');
if ($slug) {
    $mail->send($slug, $order->email, [...]);
}
```

Шаблон `new_user_password` (переменная `{{PASSWORD}}`) используется модулем при
отправке нового пароля пользователю; не удаляйте его.

---

## 7. Виджеты дашборда (`/admin/`)

Верхний ряд карточек — классы `yii\base\Widget`. Таблица `admin_widget`: записи с
`user_id = NULL` — «шаблоны», доступные всем; пользователь может скрывать,
переставлять и добавлять виджеты по имени класса (создаются копии с его `user_id`).

Класс:

```php
<?php
namespace app\widgets\admin;

use app\models\Order;
use Yii;
use yii\base\Widget;

final class OrdersTodayWidget extends Widget
{
    public function run(): string
    {
        if (!Yii::$app->user->can(Order::class . '\view')) {
            return '';
        }
        $count = Order::find()->where(['>=', 'created_at', strtotime('today')])->count();
        return $this->render('orders-today', ['count' => $count]);
    }
}
```

Вью `app/widgets/admin/views/orders-today.php` — обязательная обёртка и меню
«Переместить/Скрыть» (скопируйте разметку из
`vendor/mitisk/yii2-admin/widgets/views/index/user.php`); `data-name` = короткое имя класса:

```php
<div class="wg-chart-default dashboard-top-widget-item" data-name="OrdersTodayWidget">
    <div class="dropdown default">
        <button class="btn btn-secondary dropdown-toggle" data-bs-offset="0,-16" type="button" data-bs-toggle="dropdown">
            <span class="icon-more"><i class="icon-more-horizontal"></i></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dashboard-top-widget-item-move" href="javascript:void(0);">Переместить</a></li>
            <li><a class="dashboard-top-widget-item-hide" href="javascript:void(0);">Скрыть</a></li>
        </ul>
    </div>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap14">
            <div class="image"><i class="icon-shopping-bag"></i></div>
            <div>
                <div class="body-text mb-2">Заказов сегодня</div>
                <h4><?= (int)$count ?></h4>
            </div>
        </div>
    </div>
</div>
```

Регистрация шаблона виджета (миграция):

```php
$this->insert('{{%admin_widget}}', [
    'alias'     => 'OrdersTodayWidget',               // короткое имя класса
    'user_id'   => null,
    'class'     => '\\app\\widgets\\admin\\OrdersTodayWidget', // с ведущим \, как у встроенных
    'ordering'  => 3,
    'published' => 1,
]);
```

Центральный блок дашборда — таблица выбранного пользователем компонента
(`admin_widget_component`), настраивается в UI; кода не требует.

---

## 8. Собственные страницы/контроллеры в админке

Для нестандартных экранов (отчёты, импорт, дашборды) — контроллер проекта,
подключаемый в модуль через таблицу `admin_controller_map` (UI: `/admin/components/` →
«Controller map»).

```php
<?php
namespace app\controllers\admin;

use Mitisk\Yii2Admin\components\ExtAdminController;
use Yii;

class ReportsController extends ExtAdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access']['rules'] = [
            ['allow' => true, 'roles' => ['@'], 'matchCallback' => fn() => Yii::$app->user->can('viewReports')],
        ];
        return $behaviors;
    }

    public function actionIndex(): string
    {
        $this->view->title = 'Отчёты';
        $this->view->params['breadcrumbs'][] = 'Отчёты';
        return $this->render('index');   // @app/views/reports/index.php
    }
}
```

Правила:
- Класс **обязан** наследовать `Mitisk\Yii2Admin\components\ExtAdminController`,
  иначе карта его проигнорирует (в лог — warning).
- Namespace начинается с `app\controllers\` → вьюхи ищутся в `@app/views/<controller_id>/`.
  Для другого namespace переопределите `getViewPath()`, иначе Yii пойдёт в
  `vendor/mitisk/yii2-admin/views/<controller_id>/`.
- Лейаут админки (меню, шапка, flash) применяется автоматически; доступны классы
  темы (`wg-box`, `tf-button`, `body-title` и т.д.).
- `Yii::$app->user` внутри — админский пользователь (`AdminUser`).
- URL: `/admin/<controller_id>/<action>/`. `controller_id` не должен совпадать с
  alias компонентов и зарезервированными именами (§11).

Регистрация и пункт меню (миграция):

```php
$this->insert('{{%admin_controller_map}}', [
    'controller_id' => 'reports',
    'class'         => \app\controllers\admin\ReportsController::class,
    'config'        => null,   // или JSON: {"defaultAction":"index"}
    'enabled'       => 1,
    'created_at'    => time(),
    'updated_at'    => time(),
]);

\Mitisk\Yii2Admin\models\Menu::addToMenu('admin', [
    'text' => 'Отчёты', 'href' => '/admin/reports/', 'icon' => 'fas fa-chart-bar',
    'target' => '_self', 'rule' => 'viewReports', 'title' => '',
]);
```

Карта контроллеров читается из БД при каждом запросе (bootstrap модуля), кэша нет.

---

## 9. SEO-правила (кратко)

Таблица `seo_rules`, UI `/admin/seo-rule/`, компонент `Yii::$app->seo`
(`Mitisk\Yii2Admin\components\SeoManager`). На сайте: в лейауте `Yii::$app->seo->register();`,
в экшене `Yii::$app->seo->setContext(['category_name' => ..., 'count' => ...])`;
в правилах плейсхолдеры `{category_name}`. Подробности и примеры паттернов — в README модуля.

---

## 10. Подводные камни

1. **Таблица `user` и RBAC-таблицы `auth_*` создаются миграцией модуля.** Если в проекте
   уже есть такие таблицы — миграция упадёт; разводите через `tablePrefix` или адаптируйте.
2. **PK только `id`.** Составные/иные ключи не поддерживаются универсальным CRUD.
3. **Атрибут не в `rules()` — не сохраняется.** Самая частая причина «поле не сохраняется».
4. **Виртуальное свойство ≠ имя связи** (`$tag_ids` + `getTags()`).
5. **Пустые `list`/`data` → пустая таблица/форма.** Заполняйте оба.
6. **`findOne(['table_name' => ...])` перед созданием `AdminModel`:** записи для новых таблиц
   могли быть созданы автоматически при открытии `/admin/components/`.
7. **`authManager` и `settings` нужны в консольном конфиге**, иначе миграции с `AdminModel`
   не создадут разрешения.
8. **Имя разрешения содержит `\`:** в PHP пишите `Product::class . '\\view'`.
9. **Alias уникален, транслитерируется и не может быть зарезервированным** (§11); UrlRule
   не проверяет имена встроенных контроллеров модуля, поэтому alias `log` или `seo-rule`
   перекроет соответствующий раздел админки.
10. **URL файлов начинаются с `/web/`** — убедитесь, что они отдаются веб-сервером.
11. **`set()` настроек не задаёт label/description** — для видимых настроек используйте AR `Settings`.
12. **`MailService::send()` не бросает исключений** — проверяйте возвращаемое значение.
13. **Доступ к редактору компонентов — только `superAdminRole`**, к настройкам и письмам —
    `superAdminRole`/`admin`, к ролям — разрешение `manageRoles`.
14. **Вложенный пункт меню для компонента** — `in_menu = 0`, пункт добавлять вручную.
15. **Дата в строковой колонке** сохраняется как `Y-m-dTH:i:s`; для MySQL это допустимо,
    для других СУБД используйте int-timestamp.

---

## 11. Справочник

### Таблицы модуля
`user`, `admin_model`, `admin_model_info`, `admin_controller_map`, `admin_widget`,
`admin_widget_component`, `admin_note`, `admin_user_map`, `admin_audit_log`,
`email_templates`, `file`, `menu`, `settings`, `settings_block`, `seo_rules`,
`auth_item`, `auth_item_child`, `auth_assignment`, `auth_rule`, `migration`.

### Роли и иерархия (seed)
- `superAdminRole` → `admin`, `superAdmin`
- `admin` → `manager`, `deleteUsers`, `manageRoles`, `manageSystem`, `manageUserRoles`
- `manager` → `moderator`, `createUsers`, `updateUsers`, + `{FQCN}\*` компонентов с `in_menu = 1`
- `moderator` → `user`, `updateUsers`, `viewReports`, `viewUsers`
- `user` → `accessAdmin`; `guest`

Разрешения: `accessAdmin`, `viewUsers`, `createUsers`, `updateUsers`, `deleteUsers`,
`manageUserRoles`, `manageRoles`, `manageSystem`, `superAdmin`, `viewReports`,
`{FQCN}\view|create|update|delete`.

### Зарезервированные alias / controller_id
Технические: `index`, `error`, `captcha`, `contact`, `login`, `logout`, `default`, `auth`,
`user`, `settings`, `role`, `menu`, `components`, `ajax`, `ajax-widget`, `ajax-note`.
Встроенные контроллеры модуля (тоже не используйте): `core`, `email-template`, `seo-rule`,
`log`, `model-info`. Плюс все существующие alias компонентов и controller_id.

### Основные классы
| Класс | Назначение |
|---|---|
| `Mitisk\Yii2Admin\Module` | модуль, URL-правила, карта контроллеров, проверка версии |
| `Mitisk\Yii2Admin\models\AdminModel` | запись компонента (`admin_model`) |
| `Mitisk\Yii2Admin\core\models\AdminModel` | обёртка над AR-моделью проекта: права, колонки, поля, поиск |
| `Mitisk\Yii2Admin\core\controllers\AdminController` | универсальный CRUD |
| `Mitisk\Yii2Admin\fields\Field` и наследники | типы полей формы/таблицы |
| `Mitisk\Yii2Admin\fields\FieldsHelper` | `getFiles()`, `getValues()`, `detectLabelAttribute()` |
| `Mitisk\Yii2Admin\models\File` | файлы, `getUrl()`, `isImage()` |
| `Mitisk\Yii2Admin\components\FileStorage` | local / ftp / s3 |
| `Mitisk\Yii2Admin\components\SettingsComponent`, `models\Settings`, `models\SettingsBlock` | настройки |
| `Mitisk\Yii2Admin\components\MailService`, `models\EmailTemplate` | письма |
| `Mitisk\Yii2Admin\models\Menu`, `widgets\MenuWidget`, `components\MenuHelper` | меню |
| `Mitisk\Yii2Admin\models\AdminControllerMap`, `components\ExtAdminController` | свои контроллеры |
| `Mitisk\Yii2Admin\models\AdminWidget` | виджеты дашборда |
| `Mitisk\Yii2Admin\models\AdminModelInfo` | инструкция к компоненту |
| `Mitisk\Yii2Admin\components\AuditService`, `models\AuditLog` | аудит |
| `Mitisk\Yii2Admin\components\SeoManager`, `models\SeoRule` | SEO |
| `Mitisk\Yii2Admin\models\AdminUser` | пользователь админки, RBAC-трейт (`assignRole`, `revokeRole`, `can`) |

### URL-схема компонента
`/admin/<alias>/` список · `/admin/<alias>/create/` · `/admin/<alias>/update/?id=N` ·
`/admin/<alias>/view/?id=N` · `POST /admin/<alias>/delete/?id=N` ·
`POST /admin/<alias>/batch-delete/` (`ids[]`) · `POST /admin/<alias>/update-attribute/`
(AJAX, `items[]{id,model,attribute,value}`) · `/admin/<alias>/instruction/`.
