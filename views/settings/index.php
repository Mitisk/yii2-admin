<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/** @var yii\web\View $this */
/** @var \Mitisk\Yii2Admin\models\Settings[] $settings */
/** @var array $modelsNames */
/** @var array $emailTemplates Массив шаблонов писем */
/** @var string $modelName */
/** @var array $settingsBlock Имена и описания блоков настроек */

$this->params['breadcrumbs'][] = ['label' => 'Настройки сайта'];
$this->title = $this->params['pageHeaderText'] = 'Настройки сайта';

\Mitisk\Yii2Admin\assets\FieldFileAsset::register($this);
\Mitisk\Yii2Admin\assets\SettingsAsset::register($this);

/**
 * Собираем блоки в виде ['key' => ['title' => ..., 'content' => HTML]]
 * Каждый блок — это один таб. Порядок вставки определяет порядок табов.
 */
$tabs = [];

// GENERAL
ob_start(); ?>
    <div class="wg-box">
        <div class="left js-change-header">
            <h5 class="mb-4"><?= ArrayHelper::getValue($settingsBlock, 'GENERAL.label', 'Основные') ?></h5>
            <input type="text" name="names[GENERAL]" value="<?= ArrayHelper::getValue($settingsBlock, 'GENERAL.label', 'Основные')?>" tabindex="2" style="display: none">
            <div class="body-text"><?= ArrayHelper::getValue($settingsBlock, 'GENERAL.description', 'Это основные настройки')?></div>
            <textarea name="description[GENERAL]" style="display: none"><?= ArrayHelper::getValue($settingsBlock, 'GENERAL.description', 'Это основные настройки')?></textarea>
        </div>

        <div class="right flex-grow">

            <?php if (!Yii::$app->settings->get('GENERAL', 'api_key')) : ?>

                <div class="block-warning w-full mb-24 js-error-api">
                    <i class="icon-alert-octagon"></i>
                    <div class="body-title-2">Ваша лицензия неактивна! Получите API ключ ниже.</div>
                </div>

                <div class="block-warning type-main w-full mb-24 js-success hidden">
                    <i class="icon-alert-octagon"></i>
                    <div class="body-title-2">Лицензия активирована.</div>
                </div>

                <div class="flex flex-wrap gap10 mb-50 js-error-api">
                    <a href="#" class="tf-button js-get-api-key">Запросить API ключ</a>
                </div>

            <?php else: ?>

                <div class="block-warning type-main w-full mb-24">
                    <i class="icon-alert-octagon"></i>
                    <div class="body-title-2">Лицензия активирована.</div>
                </div>

            <?php endif; ?>

            <fieldset class="mb-10">
                <div class="body-title mb-10">Название сайта
                    <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('GENERAL', 'site_name');"></i>
                </div>
                <?php
                echo Html::textInput("Settings[4]", Yii::$app->settings->get('GENERAL', 'site_name'), ['class' => 'form-control']);
                ?>
            </fieldset>

            <?php
            $emails = Yii::$app->settings->get('GENERAL', 'admin_email', []);
            if ($emails) {
                $emails = explode(',', $emails);
            }
            ?>
            <div class="body-title mb-10">Email администратора <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('GENERAL', 'admin_email');"></i></div>
            <?php if ($emails) :?>
                <?php foreach ($emails as $email) :?>
                    <fieldset class="email mb-10 add-more-right js-add-email">
                        <input class="flex-grow" type="email" placeholder="@" name="Settings[2][]" tabindex="0" value="<?= $email ?>" aria-required="true">
                        <a href="#" class="tf-button add-more js-add-more" style="display:none;">Добавить <i class="icon-plus"></i></a>
                        <a href="#" class="tf-button add-more remove-email">Удалить <i class="icon-trash"></i></a>
                    </fieldset>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!$emails || count($emails) < 5) : ?>
                <fieldset class="email mb-10 add-more-right js-add-email">
                    <input class="flex-grow" type="email" placeholder="@" name="Settings[2][]" tabindex="0" value="" aria-required="true">
                    <a href="#" class="tf-button add-more js-add-more">Добавить <i class="icon-plus"></i></a>
                    <a href="#" class="tf-button add-more remove-email" style="display:none;">Удалить <i class="icon-trash"></i></a>
                </fieldset>
            <?php endif; ?>
            <?php if($emails && count($emails) >= 5): ?>
                <div class="block-warning type-main w-full mb-24">
                    <i class="icon-alert-octagon"></i>
                    <div class="body-title-2">Добавлено максимальное количество Email адресов</div>
                </div>
            <?php else: ?>
                <div class="block-warning type-main w-full mb-24">
                    <i class="icon-alert-octagon"></i>
                    <div class="body-title-2">Добавьте до 5 штук</div>
                </div>
            <?php endif; ?>

            <?php
            $utc = Yii::$app->settings->get('GENERAL', 'timezone');
            ?>
            <fieldset class="timezone mb-24">
                <div class="body-title mb-10">
                    Временная зона
                    <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('GENERAL', 'timezone');"></i>
                </div>
                <div class="select flex-grow">
                    <select id="timezone-select" name="Settings[3]" placeholder="Выберите зону...">
                    </select>
                </div>
                <div class="body-text mb-24">
                    Выберите часовой пояс сайта относительно времени UTC.
                    Время UTC: <b><?= (new DateTime('now', new DateTimeZone('UTC')))->format('H:i') ?></b>
                </div>
            </fieldset>

            <script>
                // Проверка на существование переменной, чтобы не сломать JS
                var selectedTimezone = <?= isset($utc) && $utc ? json_encode($utc) : 'null' ?>;
            </script>
        </div>

    </div>
    <?php
    $tabs['GENERAL'] = [
        'title' => ArrayHelper::getValue($settingsBlock, 'GENERAL.label', 'Основные'),
        'content' => ob_get_clean(),
    ];

    // ADMIN
    ob_start(); ?>
    <div class="wg-box">
        <div class="left js-change-header">
            <h5 class="mb-4"><?= ArrayHelper::getValue($settingsBlock, 'ADMIN.label', 'Панель администратора') ?></h5>
            <input type="text" name="names[ADMIN]" value="<?= ArrayHelper::getValue($settingsBlock, 'ADMIN.label', 'Панель администратора')?>" tabindex="2" style="display: none">
            <div class="body-text"><?= ArrayHelper::getValue($settingsBlock, 'ADMIN.description', 'Это настройки панели администратора')?></div>
            <textarea name="description[ADMIN]" style="display: none"><?= ArrayHelper::getValue($settingsBlock, 'ADMIN.description', 'Это настройки панели администратора')?></textarea>
        </div>

        <div class="right flex-grow">

            <fieldset class="mb-10">
                <div class="body-title mb-10">Логотип (154х52 px)
                    <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('ADMIN', 'logo');"></i>
                </div>

                <?php
                /** @var \Mitisk\Yii2Admin\models\Settings $fileSetting */
                $fileSetting = Yii::$app->settings->get('ADMIN', 'logo', getOnlyValue: false);
                $data = [];
                if ($fileSetting?->file) {
                    $data = [$fileSetting?->file->generateFileUploaderData('5')];
                }

                $filesJson = Json::encode($data); ?>

                <input type="file" class="fileuploader-single fileuploader-without-alt"
                       name="Settings[5]"
                       data-crop-width="154"
                       data-crop-height="52"
                       data-fileuploader-files='<?= $filesJson ?>'>

            </fieldset>

        </div>

    </div>
    <?php
    $tabs['ADMIN'] = [
        'title' => ArrayHelper::getValue($settingsBlock, 'ADMIN.label', 'Панель администратора'),
        'content' => ob_get_clean(),
    ];

if ($settings) {
    // Файловое хранилище (File)
    $fileSettings = [];
    foreach ($settings as $setting) {
        if ($setting->model_name === 'Mitisk\Yii2Admin\models\File') {
            $fileSettings[] = $setting;
        }
    }

    if (!empty($fileSettings)) {
        $fileBlockKey = 'Mitisk\\Yii2Admin\\models\\File';
        $fileTitle = ArrayHelper::getValue($settingsBlock, $fileBlockKey . '.label', 'Хранилище файлов');
        $tabs['FILE_STORAGE'] = [
            'title' => $fileTitle,
            'content' => $this->render('_file_settings', [
                'settings' => $fileSettings,
                'settingsBlock' => $settingsBlock,
            ]),
        ];
    }

    $groupedSettings = [];
    foreach ($settings as $setting) {
        if (in_array($setting->model_name, ['GENERAL', 'ADMIN', 'HIDDEN', 'Mitisk\Yii2Admin\models\File'])) continue;
        $groupedSettings[$setting->model_name][] = $setting;
    }

    foreach ($groupedSettings as $mName => $settingsGroup) {
        $title = ArrayHelper::getValue($modelsNames, $mName, $mName);
        if ($t = ArrayHelper::getValue($settingsBlock, $mName . '.label')) {
            $title = $t;
        }
        $tabs[$mName] = [
            'title' => $title,
            'content' => $this->render('_block', [
                'modelsNames' => $modelsNames,
                'modelName' => $mName,
                'settings' => $settingsGroup,
                'settingsBlock' => $settingsBlock,
                'emailTemplates' => $emailTemplates,
            ]),
        ];
    }
}

// Пустые пользовательские разделы (созданные через «Новый раздел», но без настроек)
$reservedKeys = ['GENERAL', 'ADMIN', 'HIDDEN', 'Mitisk\\Yii2Admin\\models\\File'];
foreach ($settingsBlock as $bKey => $bData) {
    if (isset($tabs[$bKey]) || in_array($bKey, $reservedKeys, true)) {
        continue;
    }
    $tabs[$bKey] = [
        'title' => $bData['label'] ?: $bKey,
        'content' => $this->render('_block', [
            'modelsNames' => $modelsNames,
            'modelName' => $bKey,
            'settings' => [],
            'settingsBlock' => $settingsBlock,
            'emailTemplates' => $emailTemplates,
        ]),
    ];
}

// Активный таб: ?tab=KEY или ?modelName=... или хэш из URL (#tab-KEY) — иначе первый
$activeTab = Yii::$app->request->get('tab');
if (!$activeTab || !isset($tabs[$activeTab])) {
    $activeTab = ($modelName && isset($tabs[$modelName]))
        ? $modelName
        : ($tabs ? array_key_first($tabs) : null);
}

$safeId = static fn(string $k): string => preg_replace('~[^a-z0-9_-]+~i', '_', $k);
?>

<?php $form = ActiveForm::begin(['options' => ['class' => 'form-setting form-style-2', 'enctype' => 'multipart/form-data']]); ?>

<?php if ($tabs) : ?>
    <div class="settings-tabs-nav-wrap">
        <ul class="settings-tabs" role="tablist">
            <?php foreach ($tabs as $key => $tab) :
                $id = $safeId($key);
                $isActive = $key === $activeTab;
            ?>
                <li class="settings-tabs-item" role="presentation">
                    <a class="settings-tab-btn<?= $isActive ? ' active' : '' ?>"
                       href="#settings-section-<?= $id ?>"
                       data-tab-key="<?= Html::encode($key) ?>"
                       data-target="#settings-section-<?= $id ?>"
                       role="tab">
                        <?= Html::encode($tab['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="settings-tabs-item settings-tabs-item--add" role="presentation">
                <button type="button"
                        class="settings-tab-btn settings-tab-btn--add js-add-section"
                        title="Создать новый раздел настроек">
                    <span class="settings-tab-btn__plus" aria-hidden="true">+</span>
                    <span>Новый раздел</span>
                </button>
            </li>
        </ul>
    </div>

    <?php foreach ($tabs as $key => $tab) :
        $id = $safeId($key);
        $isActive = $key === $activeTab;
    ?>
        <div class="settings-pane<?= $isActive ? ' active' : '' ?>"
             data-tab-key="<?= Html::encode($key) ?>"
             id="settings-pane-<?= $id ?>"
             <?= $isActive ? '' : 'hidden' ?>>
            <?= $tab['content'] ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$this->registerCss(<<<CSS
.settings-tabs-nav-wrap {
    position: sticky;
    top: 80px;
    z-index: 15;
    margin: 0 0 24px;
    padding: 10px 20px;
    background: var(--White, #fff);
    border: 1px solid rgba(20, 25, 38, 0.06);
    border-radius: 12px;
    box-shadow: 0 4px 18px -8px rgba(20, 25, 38, 0.12);
}
.settings-tabs {
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 4px 2px 8px;
    margin: 0;
    list-style: none;
    scrollbar-width: thin;
}
.settings-tabs::-webkit-scrollbar { height: 6px; }
.settings-tabs::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.15);
    border-radius: 3px;
}
.settings-tabs-item { flex: 0 0 auto; list-style: none; }
.settings-tab-btn {
    display: inline-flex;
    align-items: center;
    padding: 10px 18px;
    font-weight: 500;
    font-size: 14px;
    line-height: 1.2;
    color: #6b7280;
    text-decoration: none;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 10px;
    cursor: pointer;
    white-space: nowrap;
    transition: color .18s ease, background-color .18s ease, box-shadow .18s ease;
}
.settings-tab-btn:hover {
    color: #111827;
    background: rgba(17, 24, 39, 0.04);
    text-decoration: none;
}
.settings-tab-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
}
.settings-tab-btn.active {
    color: #fff;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    box-shadow: 0 6px 16px -6px rgba(79,70,229,0.55);
}
.settings-tabs-item--add { margin-left: 4px; }
.settings-tab-btn--add {
    gap: 8px;
    color: #4f46e5;
    background: rgba(99,102,241,0.08);
    border: 1.5px dashed rgba(99,102,241,0.55);
    font-weight: 600;
}
.settings-tab-btn--add:hover {
    color: #4338ca;
    background: rgba(99,102,241,0.14);
    border-style: solid;
    box-shadow: 0 8px 20px -12px rgba(79,70,229,0.55);
}
.settings-tab-btn__plus {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    font-size: 18px;
    line-height: 1;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    border-radius: 50%;
    box-shadow: 0 4px 10px -4px rgba(79,70,229,0.6);
}
/* Пэйн должен быть обычным блоком, а не flex-строкой (иначе form.form-style-2 > * ломает layout wg-box) */
form.form-style-2 > .settings-pane {
    display: block;
    flex-direction: initial;
    gap: 0;
}
.settings-pane[hidden] { display: none !important; }
/* Внутри пэйна восстанавливаем двухколоночный layout wg-box (раньше его давал form.form-style-2 > *) */
.settings-pane > .wg-box {
    display: flex;
    flex-direction: row;
    gap: 30px;
}
.settings-pane > .wg-box > .left { width: 100%; max-width: 368px; }
@media (max-width: 1200px) {
    .settings-pane > .wg-box { flex-wrap: wrap; }
    .settings-pane > .wg-box > .left { max-width: 150px; }
}
@media (max-width: 768px) {
    .settings-tab-btn { padding: 9px 14px; font-size: 13px; }
    .settings-tabs-nav-wrap { top: 70px; padding: 8px 14px; }
    .settings-pane > .wg-box > .left { max-width: unset; }
}
CSS
);
?>

<div class="bot">
    <div></div>
    <button class="tf-button w208" type="submit">Сохранить</button>
</div>

<?php ActiveForm::end(); ?>

<!-- Модальное окно добавления настройки -->
<div class="add-setting-modal" id="addSettingModal" aria-hidden="true" role="dialog" aria-labelledby="addSettingTitle">
    <div class="add-setting-modal__backdrop js-as-close"></div>
    <div class="add-setting-modal__dialog" role="document">
        <div class="add-setting-modal__head">
            <div>
                <h5 id="addSettingTitle" class="add-setting-modal__title">Новая настройка</h5>
                <div class="add-setting-modal__sub">Блок: <b class="js-as-block-label">—</b></div>
            </div>
            <button type="button" class="add-setting-modal__close js-as-close" aria-label="Закрыть">&times;</button>
        </div>
        <form class="add-setting-modal__body" id="addSettingForm" autocomplete="off">
            <input type="hidden" name="model_name" id="as-model">
            <input type="hidden" name="<?= \Yii::$app->request->csrfParam ?>" value="<?= \Yii::$app->request->csrfToken ?>">

            <div class="as-field">
                <label class="as-label" for="as-attribute">Ключ настройки <span class="req">*</span></label>
                <input type="text" class="as-input" id="as-attribute" name="attribute" required
                       pattern="[A-Za-z_][A-Za-z0-9_]*" maxlength="64"
                       placeholder="например: api_token">
                <div class="as-hint">Латиница, цифры, подчёркивания. Используется в коде: <code>Yii::$app->settings->get(...)</code></div>
            </div>

            <div class="as-field">
                <label class="as-label" for="as-label-input">Название</label>
                <input type="text" class="as-input" id="as-label-input" name="label" maxlength="255"
                       placeholder="Понятное имя для админа">
            </div>

            <div class="as-field">
                <label class="as-label">Тип данных</label>
                <div class="as-type-grid">
                    <?php
                    $types = [
                        'string' => ['Строка', 'Короткий текст'],
                        'text' => ['Текст', 'Многострочный'],
                        'integer' => ['Число', 'Целое'],
                        'float' => ['Дробное', '1.5, 3.14'],
                        'boolean' => ['Да / Нет', 'Переключатель'],
                        'json' => ['JSON', 'Структура'],
                        'file' => ['Файл', 'Загружаемый'],
                        'mail_template' => ['Email', 'Шаблон письма'],
                    ];
                    foreach ($types as $tkey => [$tlabel, $tdesc]) : ?>
                        <label class="as-type">
                            <input type="radio" name="type" value="<?= $tkey ?>" <?= $tkey === 'string' ? 'checked' : '' ?>>
                            <span class="as-type__card">
                                <span class="as-type__title"><?= $tlabel ?></span>
                                <span class="as-type__desc"><?= $tdesc ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="as-field" id="as-value-field">
                <label class="as-label" for="as-value">Значение по умолчанию</label>
                <div id="as-value-slot">
                    <input type="text" class="as-input" id="as-value" name="value" placeholder="Можно оставить пустым">
                </div>
            </div>

            <div class="as-field">
                <label class="as-label" for="as-description">Описание</label>
                <textarea class="as-input" id="as-description" name="description" rows="2"
                          placeholder="Подсказка для админа (необязательно)"></textarea>
            </div>

            <div class="as-error js-as-error" hidden></div>

            <div class="add-setting-modal__foot">
                <button type="button" class="as-btn as-btn--ghost js-as-close">Отмена</button>
                <button type="submit" class="as-btn as-btn--primary">
                    <span class="as-btn__label">Создать</span>
                    <span class="as-btn__spinner" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно создания раздела -->
<div class="add-setting-modal" id="addSectionModal" aria-hidden="true" role="dialog" aria-labelledby="addSectionTitle">
    <div class="add-setting-modal__backdrop js-sec-close"></div>
    <div class="add-setting-modal__dialog" role="document">
        <div class="add-setting-modal__head">
            <div>
                <h5 id="addSectionTitle" class="add-setting-modal__title">Новый раздел настроек</h5>
                <div class="add-setting-modal__sub">Появится отдельной вкладкой вверху страницы</div>
            </div>
            <button type="button" class="add-setting-modal__close js-sec-close" aria-label="Закрыть">&times;</button>
        </div>
        <form class="add-setting-modal__body" id="addSectionForm" autocomplete="off">
            <input type="hidden" name="<?= \Yii::$app->request->csrfParam ?>" value="<?= \Yii::$app->request->csrfToken ?>">

            <div class="as-field">
                <label class="as-label" for="sec-key">Ключ раздела <span class="req">*</span></label>
                <input type="text" class="as-input" id="sec-key" name="key" required
                       pattern="[A-Za-z_][A-Za-z0-9_\\]*" maxlength="120"
                       placeholder="например: SEO или app\models\Page">
                <div class="as-hint">Латиница, цифры, «_» и «\» (для классов моделей).<br><br>Используется в коде: <code>Yii::$app->settings->get('KEY', ...)</code></div>
            </div>

            <div class="as-field">
                <label class="as-label" for="sec-label">Название раздела <span class="req">*</span></label>
                <input type="text" class="as-input" id="sec-label" name="label" required maxlength="255"
                       placeholder="Например: SEO-настройки">
            </div>

            <div class="as-field">
                <label class="as-label" for="sec-desc">Описание</label>
                <textarea class="as-input" id="sec-desc" name="description" rows="2"
                          placeholder="Краткое описание раздела (необязательно)"></textarea>
            </div>

            <div class="as-error js-sec-error" hidden></div>

            <div class="add-setting-modal__foot">
                <button type="button" class="as-btn as-btn--ghost js-sec-close">Отмена</button>
                <button type="submit" class="as-btn as-btn--primary">
                    <span class="as-btn__label">Создать раздел</span>
                    <span class="as-btn__spinner" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$createUrl = \yii\helpers\Url::to(['/admin/settings/create-setting']);
$createSectionUrl = \yii\helpers\Url::to(['/admin/settings/create-section']);
$this->registerCss(<<<CSS
/* Кнопка добавления в блоках */
.add-setting-row { margin-top: 18px; }
.add-setting-btn {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 14px 18px;
    background: linear-gradient(135deg, rgba(99,102,241,0.04), rgba(79,70,229,0.04));
    border: 1.5px dashed rgba(99,102,241,0.45);
    border-radius: 12px;
    color: #4f46e5;
    cursor: pointer;
    text-align: left;
    transition: all .2s ease;
}
.add-setting-btn:hover {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99,102,241,0.10), rgba(79,70,229,0.08));
    transform: translateY(-1px);
    box-shadow: 0 10px 24px -14px rgba(79,70,229,0.5);
}
.add-setting-btn__plus {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    font-size: 22px;
    font-weight: 600;
    line-height: 1;
    color: #fff;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    border-radius: 50%;
    box-shadow: 0 6px 16px -6px rgba(79,70,229,0.55);
}
.add-setting-btn__text { display: flex; flex-direction: column; gap: 2px; }
.add-setting-btn__title { font-size: 14px; font-weight: 600; color: #312e81; }
.add-setting-btn__sub { font-size: 12px; color: #6b7280; font-weight: 400; }

/* Модалка */
.add-setting-modal { position: fixed; inset: 0; z-index: 1050; display: none; }
.add-setting-modal.open { display: block; }
.add-setting-modal__backdrop {
    position: absolute; inset: 0;
    background: rgba(17,24,39,0.55);
    backdrop-filter: blur(3px);
    animation: asFade .18s ease;
}
.add-setting-modal__dialog {
    position: relative;
    max-width: 640px;
    margin: 6vh auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 30px 80px -20px rgba(20,25,38,0.35);
    overflow: hidden;
    animation: asPop .22s cubic-bezier(.2,.9,.3,1.2);
}
@keyframes asFade { from {opacity:0} to {opacity:1} }
@keyframes asPop { from {opacity:0; transform: translateY(-10px) scale(.98)} to {opacity:1; transform:none} }
.add-setting-modal__head {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 22px 24px 14px;
    border-bottom: 1px solid rgba(0,0,0,.06);
}
.add-setting-modal__title { margin: 0 0 4px; font-size: 18px; font-weight: 600; color: #111827; }
.add-setting-modal__sub { font-size: 13px; color: #6b7280; }
.add-setting-modal__sub b { color: #4f46e5; font-weight: 600; }
.add-setting-modal__close {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    font-size: 24px;
    line-height: 1;
    color: #9ca3af;
    background: transparent;
    border: 0;
    border-radius: 50%;
    cursor: pointer;
    overflow: hidden;
    transition: color .15s, background-color .15s;
}
.add-setting-modal__close:hover { color: #111827; background: rgba(17,24,39,0.06); }
.add-setting-modal__body { padding: 20px 24px 8px; max-height: 70vh; overflow-y: auto; }
.add-setting-modal__foot {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 14px 24px 20px;
    border-top: 1px solid rgba(0,0,0,.06);
    background: #fafbfc;
}

.as-field { margin-bottom: 16px; }
.as-label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#374151; }
.as-label .req { color: #ef4444; }
.as-input {
    width: 100%;
    padding: 10px 12px;
    font-size: 14px;
    color: #111827;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    transition: border-color .15s, box-shadow .15s;
    font-family: inherit;
}
.as-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.18);
}
.as-hint { margin-top: 6px; font-size: 12px; color: #6b7280; }
.as-hint code { background:#f3f4f6; padding:1px 6px; border-radius:4px; font-size:11px; }

.as-type-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.as-type input { position: absolute; opacity: 0; pointer-events: none; }
.as-type__card {
    display: flex; flex-direction: column; gap: 2px;
    padding: 10px 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
    transition: all .15s;
    background: #fff;
}
.as-type__card:hover { border-color: #c7d2fe; }
.as-type input:checked + .as-type__card {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(79,70,229,0.04));
    box-shadow: 0 4px 12px -6px rgba(79,70,229,0.35);
}
.as-type__title { font-size: 13px; font-weight: 600; color: #111827; }
.as-type__desc { font-size: 11px; color: #6b7280; }

.as-error {
    margin: 8px 0 4px;
    padding: 10px 12px;
    color: #991b1b;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 13px;
}
.as-btn {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all .15s;
    display: inline-flex; align-items: center; gap: 8px;
}
.as-btn--ghost { color:#374151; background:#fff; border-color:#d1d5db; }
.as-btn--ghost:hover { background:#f9fafb; }
.as-btn--primary {
    color: #fff;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    box-shadow: 0 8px 20px -10px rgba(79,70,229,0.6);
}
.as-btn--primary:hover { filter: brightness(1.05); }
.as-btn--primary[disabled] { opacity:.7; cursor: not-allowed; }
.as-btn__spinner {
    width:14px; height:14px; border:2px solid rgba(255,255,255,.4);
    border-top-color:#fff; border-radius:50%;
    display:none; animation: asSpin .7s linear infinite;
}
.as-btn.loading .as-btn__spinner { display:inline-block; }
@keyframes asSpin { to { transform: rotate(360deg); } }

@media (max-width: 640px) {
    .as-type-grid { grid-template-columns: repeat(2, 1fr); }
    .add-setting-modal__dialog { margin: 0; border-radius: 0; min-height: 100vh; }
}
CSS
);

$this->registerJs(<<<JS
(function() {
    var modal   = document.getElementById('addSettingModal');
    var form    = document.getElementById('addSettingForm');
    if (!modal || !form) return;

    var modelInput = document.getElementById('as-model');
    var blockLabel = modal.querySelector('.js-as-block-label');
    var errorBox   = modal.querySelector('.js-as-error');
    var submitBtn  = form.querySelector('button[type=submit]');
    var valueSlot  = document.getElementById('as-value-slot');

    function buildValueField(type) {
        switch (type) {
            case 'text':
            case 'json':
                return '<textarea class="as-input" name="value" id="as-value" rows="3" placeholder="Можно оставить пустым"></textarea>';
            case 'integer':
            case 'float':
                return '<input type="number" step="' + (type === 'float' ? 'any' : '1') + '" class="as-input" name="value" id="as-value" placeholder="0">';
            case 'boolean':
                return '<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">'
                     + '<input type="hidden" name="value" value="0">'
                     + '<input type="checkbox" name="value" value="1" id="as-value"> Включено</label>';
            case 'file':
                return '<div class="as-hint">Файл загружается позже — через обычное сохранение настроек.</div>'
                     + '<input type="hidden" name="value" value="">';
            default:
                return '<input type="text" class="as-input" name="value" id="as-value" placeholder="Можно оставить пустым">';
        }
    }

    // Переключение типа
    form.addEventListener('change', function(e) {
        if (e.target.name === 'type') {
            valueSlot.innerHTML = buildValueField(e.target.value);
        }
    });

    function open(modelName, blockTitle) {
        modelInput.value = modelName;
        blockLabel.textContent = blockTitle || modelName;
        errorBox.hidden = true;
        errorBox.textContent = '';
        form.reset();
        modelInput.value = modelName;
        var first = form.querySelector('input[name=type][value=string]');
        if (first) first.checked = true;
        valueSlot.innerHTML = buildValueField('string');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setTimeout(function(){ document.getElementById('as-attribute').focus(); }, 80);
    }
    function close() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-add-setting').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var model = btn.getAttribute('data-model');
            // Подтягиваем заголовок таба или блока
            var tabBtn = document.querySelector('.settings-tab-btn[data-tab-key="' + model + '"]');
            var title  = tabBtn ? tabBtn.textContent.trim() : model;
            open(model, title);
        });
    });
    modal.querySelectorAll('.js-as-close').forEach(function(el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        errorBox.hidden = true;
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        var fd = new FormData(form);
        fetch('$createUrl', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (res && res.ok) {
                close();
                window.location.reload();
            } else {
                errorBox.textContent = (res && res.error) ? res.error : 'Не удалось создать настройку';
                errorBox.hidden = false;
            }
        })
        .catch(function() {
            errorBox.textContent = 'Сетевая ошибка. Попробуйте ещё раз.';
            errorBox.hidden = false;
        })
        .finally(function() {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        });
    });
})();

(function() {
    var modal = document.getElementById('addSectionModal');
    var form  = document.getElementById('addSectionForm');
    if (!modal || !form) return;

    var errorBox  = modal.querySelector('.js-sec-error');
    var submitBtn = form.querySelector('button[type=submit]');
    var keyInput  = document.getElementById('sec-key');

    function open() {
        errorBox.hidden = true;
        errorBox.textContent = '';
        form.reset();
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setTimeout(function(){ keyInput.focus(); }, 80);
    }
    function close() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-add-section').forEach(function(btn) {
        btn.addEventListener('click', open);
    });
    modal.querySelectorAll('.js-sec-close').forEach(function(el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        errorBox.hidden = true;
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        var fd = new FormData(form);
        fetch('$createSectionUrl', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (res && res.ok) {
                close();
                window.location.hash = '#tab-' + res.key;
                window.location.reload();
            } else {
                errorBox.textContent = (res && res.error) ? res.error : 'Не удалось создать раздел';
                errorBox.hidden = false;
            }
        })
        .catch(function() {
            errorBox.textContent = 'Сетевая ошибка. Попробуйте ещё раз.';
            errorBox.hidden = false;
        })
        .finally(function() {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        });
    });
})();
JS
);
?>

<?php
// Синхронизация активного таба с URL-хэшем (чтобы перезагрузка/сабмит сохраняли вкладку)
$this->registerJs(<<<JS
(function() {
    var links = Array.prototype.slice.call(document.querySelectorAll('.settings-tabs .settings-tab-btn'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('.settings-pane'));
    if (!links.length) return;

    function activate(key) {
        var found = false;
        links.forEach(function(a) {
            var on = a.getAttribute('data-tab-key') === key;
            a.classList.toggle('active', on);
            if (on) found = true;
        });
        panes.forEach(function(p) {
            var on = p.getAttribute('data-tab-key') === key;
            p.classList.toggle('active', on);
            if (on) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
        });
        return found;
    }

    links.forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var key = a.getAttribute('data-tab-key');
            if (activate(key)) {
                history.replaceState(null, '', '#tab-' + key);
            }
        });
    });

    // Восстановить активный таб из хэша при загрузке
    if (window.location.hash.indexOf('#tab-') === 0) {
        activate(window.location.hash.substring(5));
    }
})();
JS
);
?>
