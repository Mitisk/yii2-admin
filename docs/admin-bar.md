# Панель администратора на сайте (Admin Bar) — план

> Статус: этап 1 реализован в версии 1.7.0 (ветка `admin-bar`). Этап 2 реализован:
> SEO-панель, «Смотреть как гость», «Показывать черновики» и правка текстовых блоков
> раздела «Контент» (ветка `content-blocks`). Этап 3 — в планах.

Плавающая панель для залогиненного администратора поверх страниц сайта:
быстрый переход в админку, контекст текущей страницы («Редактировать запись»),
действия, меню, командная палитра и inline-правка текста. Подключается одной
строкой в лейауте сайта, владелец проекта сам решает, встраивать её или нет.

## 1. Архитектура

### Точка входа

```php
<?= \Mitisk\Yii2Admin\widgets\AdminBar::widget() ?>   // в лейауте сайта перед </body>
```

Для неадмина виджет возвращает пустую строку: ни HTML, ни ассетов, ни запросов
к БД (кроме проверки identity). Внутри `/admin/` виджет молчит.

### Проверка администратора на фронте

`Yii::$app->get('adminUser')` (регистрируется модулем при bootstrap) + `isGuest`
(автологин по cookie `_admin_identity`) + `authManager->checkAccess($id, 'accessAdmin')`.
Фронтовый `Yii::$app->user` не трогаем. Если модуль не в `bootstrap`, виджет пишет
warning в лог и ничего не выводит.

### Два режима подключения (`ADMIN.bar_mode`)

- `server` (по умолчанию): виджет рендерит `<admin-bar>` и состояние в
  `<script type="application/json">` (не исполняется, дружит с CSP), регистрирует
  `AdminBarAsset`, ставит `Cache-Control: private, no-store`.
- `client`: виджет выводит только загрузчик (~1 КБ), который запрашивает
  `/admin/bar/state/`. Неадмину эндпоинт отвечает 401, на странице ничего не
  появляется. Для сайтов с полностраничным кэшем. В этом режиме класс и id
  контекстной модели передаются в HTML атрибутами загрузчика.

### Изоляция

Shadow DOM кастомного элемента `<admin-bar>`; CSS подключается внутрь shadow root.
Иконки — инлайновый SVG, шрифт — системный стек. Ванильный JS без jQuery.
Бюджет: JS ≤ 30 КБ, CSS ≤ 10 КБ (min).

### Контракт состояния (JSON)

```json
{
  "version": "1.7.0",
  "user":     {"id": 1, "name": "Администратор", "avatar": "/web/...", "roles": ["superAdminRole"]},
  "urls":     {"dashboard": "/admin", "profile": "/admin/user/update?id=1", "logout": "/admin/logout",
               "blocks": "/admin/content-block", "blockEdit": "/admin/content-block/update?modal=1&key="},
  "csrf":     {"param": "_csrf", "token": "..."},
  "endpoints":{"state": "/admin/bar/state", "action": "/admin/bar/action", "attribute": "/admin/bar/attribute",
               "block": "/admin/bar/block"},
  "context":  {"url": "/about", "route": "page/view",
               "model": {"class": "Mitisk\\Yii2Admin\\models\\Page", "id": 12, "label": "О компании",
                         "component": {"alias": "page", "name": "Страницы"},
                         "urls": {"index": "...", "update": "...", "create": "..."},
                         "editUrl": "/admin/page/update?id=12&modal=1", "key": "page:12",
                         "status": {"label": "Черновик", "kind": "draft"},
                         "actions": [{"id": "page-publish", "label": "Опубликовать", "icon": "check",
                                      "url": "/admin/page/publish?id=12", "post": true, "reload": true}]}},
  "menu":     [],
  "actions":  [{"id": "clear-cache", "label": "Очистить кэш", "icon": "refresh", "confirm": "Очистить кэш сайта?"}],
  "panels":   [{"id": "seo", "label": "SEO", "icon": "search", "items": [], "url": ""}],
  "badges":   {"update": "1.7.1"},
  "impersonation": {"active": false, "returnUrl": "/admin/user/stop-impersonate/"},
  "features": {"inlineEdit": true, "drafts": false, "blocks": true, "pages": true},
  "prefs":    {"position": "bottom", "theme": "dark", "hotkey": "Alt+Shift+A"},
  "pages":    [{"title": "О компании", "path": "/about", "status": "Опубликована", "url": "/about"}],
  "view":     {"guest": false, "drafts": false, "cookies": {"guest": "ab_guest", "drafts": "ab_drafts"}},
  "assets":   {"js": "...", "css": "...", "blocks": ".../js/admin-bar-blocks.min.js"}
}
```

Все адреса строит `Url::to()`, поэтому они совпадают с правилами `urlManager` сайта
(со слешем на конце адреса давали 301, и POST превращался в GET). Действие с `post: true`
панель отправляет POST-запросом на `url` и при `reload: true` перезагружает страницу.
На странице 404 в `actions` появляется `create-page` с адресом формы новой страницы.

### Расширяемость

1. **PHP-API** `Yii::$app->adminBar`: `setModel($ar)`, `addAction(id, label, options)`,
   `addPanel(id, label, items, options)`, `setContext(key, value)`. Вызывается из
   контроллеров сайта.
2. **Событие** `AdminBarState::EVENT_BUILD` (class-level `Event::on`): обработчик
   получает объект состояния и дописывает панели/действия. Результат берётся из
   объекта, а не из копии массива.
3. **Регистр серверных действий** `AdminBarComponent::$serverActions`:
   `id => [label, icon, permission, confirm, handler]`. Панель шлёт
   `POST /admin/bar/action/ {id}`. Первое действие — `clear-cache` (`manageSystem`).
4. **Реестр встроенных моделей** `AdminBarComponent::$models` — модели, которых нет в
   `admin_model`, но с которыми бар должен работать: чип контекста, правка на месте,
   модалка с формой. Модуль регистрирует `Page`; сайт дописывает свои через конфиг:

   ```php
   'adminBar' => [
       'class' => \Mitisk\Yii2Admin\components\AdminBarComponent::class,
       'models' => [
           \app\models\Product::class => [
               'alias' => 'product', 'name' => 'Товары', 'label' => 'name',
               'permissions' => ['view' => 'viewProducts', 'update' => 'editProducts', 'create' => 'editProducts'],
               'routes' => ['index' => '/catalog/admin/index', 'update' => '/catalog/admin/update', 'create' => '/catalog/admin/create'],
           ],
       ],
   ],
   ```

   `describe()`, `isManaged()`, `canUpdate()`, эндпоинт `attribute` и `AdminBar::editable()`
   сначала смотрят в реестр, потом в `admin_model`. Форма по `routes.update` с параметром
   `modal=1` открывается в модалке бара, если по `postMessage({type: 'ab-block-saved', key})`
   сообщит о сохранении.

### Сервер

`BarController` (`/admin/bar/...`), алиас `bar` зарезервирован:

- `GET state` — JSON состояния; гостю 401 (маршрут исключён из редиректа на логин).
- `POST action` — выполнить серверное действие из регистра с проверкой права.
- `POST attribute` — inline-правка: модель должна быть компонентом админки
  (`admin_model.view = 1`) или встроенной моделью из реестра, право `{FQCN}\update`
  (у встроенной — её `permissions.update`) или `admin`, атрибут из
  `safeAttributes()`, `validate([$attr])`, сохранение, `AuditService::log`.

### Inline-правка (задел)

```php
<?= \Mitisk\Yii2Admin\widgets\AdminBar::editable($product, 'name') ?>
```

Для неадмина выводит просто значение. Для админа с правом `update` оборачивает в
`<span data-ab-model data-ab-id data-ab-attr data-ab-type="text">`. Типы
`text|html|block|image` заложены в разметке; в первом релизе работает `text`.

### Настройки

Раздел `ADMIN`: `bar_enabled` (boolean), `bar_mode` (server|client),
`bar_position` (bottom|top). Личные предпочтения (свёрнута, тема) — в `localStorage`.

### Безопасность

Панель никогда не попадает в HTML неадмина; эндпоинты только под правами; POST с
CSRF; `Cache-Control: private, no-store` при серверном рендере; при имперсонации —
плашка и кнопка возврата.

## 2. Первый релиз (1.7.0)

Внешний вид: плавающий «остров» внизу по центру, тёмное стекло с размытием,
скругления, мягкая тень, появление снизу. Слева аватар и имя, затем чип контекста
с кнопкой «Редактировать», быстрые действия, кнопка меню, бейджи, свернуть.
Поповеры открываются вверх. Светлая тема переключателем. На мобильных — только
иконки. Свёрнутая панель — круглая кнопка в углу.

1. `widgets/AdminBar.php`, `components/AdminBarComponent.php`, `components/AdminBarState.php`.
2. `controllers/BarController.php`: `state`, `action`, `attribute`; действие `clear-cache`.
3. `assets/AdminBarAsset.php`, исходники `assets/src/admin-bar/{bar.js,bar.css,loader.js}`,
   минифицированные копии в `assets/js/` и `assets/css/`.
4. UI: панель, поповер меню, чип контекста, действия с подтверждением, тосты,
   сворачивание, `Alt+Shift+A`, командная палитра `Ctrl+K` (меню, компоненты,
   действия), режим «Редактировать» с правкой текста (Enter — сохранить, Esc — отмена).
5. `AdminBar::editable()` и тип `text`.
6. Бейдж доступного обновления → `/admin/default/update`.
7. Плашка имперсонации.
8. Миграция с настройками `bar_*`, `bar` в `ReservedAlias`, исключения маршрутов.
9. README и CLAUDE.md.
10. Проверка на тестовом проекте: гость не видит панель, админ видит, контекст,
    правка с валидацией, `clear-cache`, режим `client` → 401 гостю.

## 3. Следующие этапы

- **Этап 2**:
  - [x] SEO-панель на данных `SeoManager`: сработавшее правило, его значения с длиной,
    ссылка на правку или на создание правила под текущий URL (форма предзаполняет паттерн).
  - [x] «Смотреть как гость»: cookie `ab_guest`, панель сворачивается в кнопку выхода
    из режима, `editable()` отдаёт чистое значение, черновики выключены.
  - [x] «Показывать черновики»: cookie `ab_drafts`, сайт спрашивает
    `Yii::$app->adminBar->showDrafts()` в своих выборках; тумблер виден на страницах,
    которые это сделали, в client-режиме — по `draftsToggle`. Во включённом режиме
    в панели горит индикатор «Черновики».
  - [x] Тип `block`: блоки раздела «Контент» (`ContentBlock::widget`). Текст правится
    на месте через `POST /admin/bar/block`, остальные типы — в модальном окне с формой
    админки в iframe (Trumbowyg и кроп живут внутри iframe). Панель «Блоки на странице»
    и пункты палитры собираются из `[data-ab-block]`. Код правки вынесен в
    `admin-bar-blocks.min.js` и грузится при первой правке.
  - Бюджет JS превышен: `admin-bar.min.js` 32,4 КБ при плане 30 КБ.
- **Этап 3** (после планировщика и медиатеки): техпанель (время, SQL), тип
  `image`, заметки-булавки, бейджи задач и заявок, «создать редирект» на 404.
  - [x] «Создать страницу по этому адресу» на 404 и страницы раздела «Контент» в баре:
    чип со статусом, «Опубликовать» / «Снять с публикации», правка заголовка на месте и
    текста в модалке, поиск страниц в палитре (ветка `pages`, см. `docs/pages.md` приложения).
  - Бюджет: `admin-bar.min.js` 34,1 КБ, `admin-bar.min.css` 10,0 КБ при плане 30 и 10 КБ.

Каждый этап только добавляет действия, панели и типы в существующие точки
расширения; разметка сайта и API контроллеров не меняются.
