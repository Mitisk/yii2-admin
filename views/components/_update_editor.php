<?php
/**
 * Editor partial for the component update form (step 2) — tabs and canvas.
 *
 * @category View
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 *
 * @var \yii\web\View                                $this
 * @var \Mitisk\Yii2Admin\models\AdminModel          $model
 * @var \Mitisk\Yii2Admin\models\AdminModelInfo|null $info
 * @var \yii\widgets\ActiveForm                      $form
 * @var string                                       $host
 * @var array                                        $allColumnsNames
 * @var array                                        $effectiveLabels
 * @var array                                        $dropdownItems
 * @var array                                        $requiredColumns
 *
 * @php 8.0
 */

use Mitisk\Yii2Admin\assets\TrumbowygAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

TrumbowygAsset::register($this);

// Лейблы из холста (высший приоритет для Grid-колонок)
$canvasLabels = [];
if ($model->data) {
    foreach (json_decode($model->data, true) ?: [] as $item) {
        if (!empty($item['name']) && !empty($item['label'])) {
            $canvasLabels[$item['name']] = $item['label'];
        }
    }
}
?>

<?php /* Заголовок страницы */ ?>
<div class="comp-page-header wg-box mb-16">
    <div>
        <div class="body-title-2" style="color:#64748b;font-size:13px;margin-bottom:2px;">Компонент</div>
        <h4><?php echo Html::encode($model->name) ?></h4>
    </div>
    <button class="tf-button js-check-to-save" type="submit">
        <i class="fas fa-save me-1"></i> Сохранить
    </button>
</div>

<?php /* Вкладки */ ?>
<ul class="nav comp-nav-tabs" id="compTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-general-btn" data-bs-toggle="tab"
                data-bs-target="#tab-general" type="button" role="tab">
            <i class="fas fa-cog me-1"></i> Основные
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-grid-btn" data-bs-toggle="tab"
                data-bs-target="#tab-grid" type="button" role="tab">
            <i class="fas fa-table me-1"></i> Таблица (Grid)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-links-btn" data-bs-toggle="tab"
                data-bs-target="#tab-links" type="button" role="tab">
            <i class="fas fa-link me-1"></i> Ссылки
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-canvas-btn" data-bs-toggle="tab"
                data-bs-target="#tab-canvas" type="button" role="tab">
            <i class="fas fa-paint-brush me-1"></i> Визуальный холст
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-instruction-btn" data-bs-toggle="tab"
                data-bs-target="#tab-instruction" type="button" role="tab">
            <i class="fas fa-book-open me-1"></i> Инструкция
        </button>
    </li>
</ul>

<div class="tab-content comp-tab-content" id="compTabContent">

    <?php /* ─── TAB: Основные ─────────────────────────────── */ ?>
    <div class="tab-pane fade" id="tab-general" role="tabpanel">

        <div class="wg-box mb-20">
            <h4>Базовые настройки</h4>

            <div class="row" style="flex-wrap:wrap;gap:0;">
                <fieldset class="col-md-6 name pe-md-2 mb-20">
<?php
echo $form->field($model, 'name')
    ->textInput(['maxlength' => 255])
    ->label('Название <span class="tf-color-1">*</span>');
?>
                </fieldset>
                <fieldset class="col-md-6 name ps-md-2 mb-20">
<?php
$tpl = '{label}<div class="input-group">{prefix}{input}{copy}</div>{error}';
$pfx = '<span class="input-group-text" style="font-size:13px;padding-right:0;">'
    . Html::encode($host) . '/admin/</span>';
$cpy = '<div class="box-coppy"><div class="coppy-content" style="display:none">'
    . Html::encode($host) . '/admin/' . Html::encode($model->alias)
    . '</div><i class="icon-copy button-coppy"></i></div>';
echo $form->field(
    $model,
    'alias',
    [
        'template'     => $tpl,
        'parts'        => ['{prefix}' => $pfx, '{copy}' => $cpy],
        'labelOptions' => ['class' => 'body-title mb-10'],
    ]
)->textInput(
    [
        'maxlength' => 255,
        'class'     => 'form-control',
        'style'     => 'padding-left:2px',
    ]
)->label('URL-адрес (Slug)');
?>
                </fieldset>
            </div>

            <fieldset class="name">
<?php
echo $form->field($model, 'model_class')
    ->textInput(
        [
            'maxlength'   => 255,
            'placeholder' => 'Например: app\models\Product',
        ]
    )
    ->label('Путь к классу модели (Namespace)')
    ->hint(
        'Укажите полный путь к Active Record классу,'
        . ' который будет управлять данными.'
    );
?>
            </fieldset>

            <fieldset class="name">
<?php
echo $form->field($model, 'file_path')
    ->textInput(
        [
            'maxlength'   => 255,
            'placeholder' => 'Например: /web/items/',
        ]
    )
    ->label('Путь для файлов на сервере')
    ->hint(
        '<i class="fas fa-info-circle me-1"></i>'
        . 'Если указан — файлы этого компонента будут'
        . ' сохраняться и отдаваться по этому пути'
        . ' вместо глобального хранилища (S3/FTP).'
    );
?>
            </fieldset>
        </div>

        <?php if ($allColumnsNames) : ?>

        <div class="wg-box mb-20">
            <h4>Настройка администрирования</h4>

            <div class="flex gap10 mb-20">
                <?php echo Html::activeCheckbox($model, 'can_create', ['class' => 'total-checkbox']) ?>
                <label for="<?php echo Html::getInputId($model, 'can_create') ?>" class="body-text">
                    Показывать кнопку «Добавить»
                </label>
            </div>

            <div class="flex gap10 mb-24">
                <?php echo Html::activeCheckbox($model, 'non_encode', ['class' => 'total-checkbox']) ?>
                <label for="<?php echo Html::getInputId($model, 'non_encode') ?>" class="body-text">
                    Разрешить HTML-код в ячейках
                </label>
            </div>

            <fieldset class="select">
            <?php
            echo $form->field($model, 'admin_label')
                ->dropDownList($dropdownItems, ['class' => 'tom-select'])
                ->label('Основной заголовок записи');
            ?>
            </fieldset>
            <div class="block-warning type-main w-full">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Это поле, которое будет ссылкой на редактирование или отображаться в Select2 при связях.</div>
            </div>
        </div>

        <?php endif; ?>
    </div>
    <?php /* ─── /TAB: Основные ───────────────────────────── */ ?>

    <?php /* ─── TAB: Таблица (Grid) ──────────────────────── */ ?>
    <div class="tab-pane fade" id="tab-grid" role="tabpanel">

        <?php if ($allColumnsNames) : ?>

        <div class="wg-box mb-20">
            <h4>Сортировка по умолчанию</h4>
            <div class="row" style="flex-wrap:wrap;">
                <fieldset class="select flex-grow col-md-6 pe-md-2">
                    <label class="body-title mb-10">По какому полю сортировать</label>
                    <?php
                    echo $form->field(
                        $model,
                        'default_sort_attribute',
                        ['template' => '{input}{error}']
                    )->dropDownList(
                        ArrayHelper::merge(
                            [null => '--- По умолчанию (PK) ---'],
                            $dropdownItems
                        ),
                        ['class' => 'tom-select']
                    );
                    ?>
                </fieldset>
                <fieldset class="select flex-grow col-md-6 ps-md-2">
                    <label class="body-title mb-10">Направление</label>
                    <?php
                    echo $form->field(
                        $model,
                        'default_sort_direction',
                        ['template' => '{input}{error}']
                    )->dropDownList(
                        [
                            SORT_ASC  => 'По возрастанию (А → Я / 0 → 9)',
                            SORT_DESC => 'По убыванию (Я → А / 9 → 0)',
                        ],
                        ['class' => 'tom-select']
                    );
                    ?>
                </fieldset>
            </div>
            <div class="block-warning type-main w-full mt-10">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Если поле не выбрано, сортировка будет по Первичному ключу (ID) по убыванию.</div>
            </div>
        </div>

        <div class="wg-box mb-20">
            <h4>
                <i class="fas fa-columns text-secondary me-2"></i>Колонки таблицы
            </h4>
            <div class="grid-column-list js-gc-sortable">
                <?php foreach ((array)$model->list as $key => $columnData) : ?>
                    <?php if (!ArrayHelper::getValue($columnData, 'name')) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php
                    $isLinkSlot = is_string($key)
                        && strncmp($key, 'admin_link_', 11) === 0;
                    ?>
                    <?php if ($isLinkSlot) : ?>
                        <?php echo $this->render(
                            '_list_item_link_slot',
                            [
                                'model'      => $model,
                                'column'     => $key,
                                'columnData' => $columnData,
                            ]
                        ) ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php
                    $label = $canvasLabels[$key]
                        ?? $effectiveLabels[$key]
                        ?? ArrayHelper::getValue($columnData, 'name');
                    echo $this->render(
                        '_list_item',
                        [
                            'model'           => $model,
                            'column'          => $key,
                            'columnData'      => $columnData,
                            'requiredColumns' => $requiredColumns,
                            'name'            => $label,
                        ]
                    );
                    ?>
                <?php endforeach; ?>
            </div>
            <button type="button"
                    class="tf-button style-2 mt-10 js-link-slot-add">
                <i class="icon-plus"></i> Добавить колонку для ссылок
            </button>
        </div>

        <div class="wg-box mb-20" id="link-slots-box">
            <h4>
                <i class="fas fa-link text-secondary me-2"></i>Пул ссылок
            </h4>
            <p class="body-text" style="font-size:13px;color:#64748b;">
                Перетаскивайте ссылки из пула ниже в drop-зону нужного слота
                в списке колонок выше. Слоты участвуют в общей сортировке
                колонок таблицы наравне с остальными полями.
            </p>
            <div id="links-pool" class="links-pool"></div>
        </div>

        <?php endif; ?>
    </div>
    <?php /* ─── /TAB: Таблица (Grid) ────────────────────── */ ?>

    <?php /* ─── TAB: Ссылки ──────────────────────────────── */ ?>
    <?php require __DIR__ . '/_update_links.php' ?>
    <?php /* ─── /TAB: Ссылки ─────────────────────────────── */ ?>

    <?php /* ─── TAB: Визуальный холст ───────────────────── */ ?>
    <div class="tab-pane fade show active" id="tab-canvas" role="tabpanel">

        <?php if ($allColumnsNames) : ?>
        <div class="canvas-layout">

            <?php /* Сайдбар с доступными полями */ ?>
            <div class="canvas-sidebar">
                <h6 style="font-weight:700;margin-bottom:6px;">
                    <i class="fas fa-database text-secondary me-1"></i> Поля модели
                </h6>
                <p class="body-text" style="font-size:13px;color:#64748b;margin-bottom:10px;line-height:1.3;">
                    Каждое поле можно использовать один раз. Перетащите на холст.
                </p>
                <div id="canvas-available-fields" class="canvas-fields-list"></div>

                <?php if (!empty($allPublicAttributesForJs)) : ?>
                <div class="canvas-section-title">Публичные свойства</div>
                <p class="body-text" style="font-size:13px;color:#64748b;margin-bottom:10px;line-height:1.3;">
                    Виртуальные атрибуты модели.
                </p>
                <div id="canvas-public-fields" class="canvas-fields-list"></div>
                <?php endif; ?>

                <div class="canvas-section-title">
                    <i class="fas fa-link me-1"></i> Ссылки-кнопки
                </div>
                <p class="body-text" style="font-size:13px;color:#64748b;margin-bottom:10px;line-height:1.3;">
                    Перетащите, чтобы разместить кнопку в форме.
                    Добавляйте новые во вкладке «Ссылки».
                </p>
                <div id="canvas-link-fields" class="canvas-fields-list"></div>

                <div class="canvas-section-title">Оформление и макет</div>
                <p class="body-text" style="font-size:13px;color:#64748b;margin-bottom:10px;line-height:1.3;">
                    Визуальные блоки, можно использовать многократно.
                </p>
                <div id="canvas-content-fields" class="canvas-fields-list">
                    <div class="canvas-field-pill" data-type="header" data-is-content="true">
                        <i class="fas fa-grip-vertical drag-icon"></i>
                        <span><i class="fas fa-heading text-secondary me-1"></i> Заголовок</span>
                    </div>
                    <div class="canvas-field-pill" data-type="paragraph" data-is-content="true">
                        <i class="fas fa-grip-vertical drag-icon"></i>
                        <span><i class="fas fa-align-left text-secondary me-1"></i> Абзац текста</span>
                    </div>
                    <div class="canvas-field-pill" data-type="divider" data-is-content="true">
                        <i class="fas fa-grip-vertical drag-icon"></i>
                        <span><i class="fas fa-minus text-secondary me-1"></i> Разделитель (HR)</span>
                    </div>
                </div>
            </div>

            <?php /* Основная область холста */ ?>
            <div>
                <div class="flex align-items-center justify-content-between mb-16">
                    <div>
                        <div class="body-title" style="font-size:16px;">Холст формы редактирования</div>
                        <div class="body-text" style="font-size:13px;color:#64748b;">
                            Кликните поле — откроются настройки ширины и параметров
                        </div>
                    </div>
                    <button type="button" class="tf-button style-2" onclick="canvasClear()">
                        <i class="fas fa-trash-alt me-1"></i> Очистить
                    </button>
                </div>

                <div id="form-canvas" class="canvas-drop-area">
                    <div class="canvas-empty-state" id="canvas-empty">
                        <i class="fas fa-layer-group"></i>
                        <div class="body-title" style="font-size:16px;">Холст пуст</div>
                        <div class="body-text" style="font-size:14px;">Перетащите поля из колонки слева</div>
                    </div>
                </div>
            </div>

        </div>
        <?php else: ?>
        <div class="wg-box mb-20">
            <div class="block-warning type-main w-full">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Сначала укажите класс модели на вкладке «Основные» и сохраните.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php echo $form->field($model, 'data')->hiddenInput()->label(false) ?>
    </div>
    <?php /* ─── /TAB: Визуальный холст ─────────────────── */ ?>

    <?php /* ─── TAB: Инструкция ──────────────────────────── */ ?>
    <div class="tab-pane fade" id="tab-instruction" role="tabpanel">
        <div class="wg-box mb-20">
            <h4>
                <i class="fas fa-book-open text-secondary me-2"></i>
                Инструкция для контент-менеджеров
            </h4>
            <p class="body-text mb-20" style="font-size:14px;color:#64748b;">
                Напишите здесь инструкции, правила заполнения или особенности
                работы с этой сущностью. Они будут отображаться для
                пользователей над списком записей.
            </p>
            <fieldset class="name">
                <label class="body-title mb-10">
                    Текст инструкции
                    <span class="body-text">(поддерживается HTML)</span>
                </label>
                <textarea id="instruction-content"
                          name="AdminModelInfo[content]"
                          rows="12" class="form-control"
                          placeholder="Например: При добавлении новости..."
                ><?php
                    echo Html::encode($info->content ?? '');
                ?></textarea>
            </fieldset>
        </div>
    </div>
    <?php /* ─── /TAB: Инструкция ─────────────────────────── */ ?>

</div>
<?php /* ─── /tab-content ─────────────────────────────── */ ?>

<?php
$this->registerJs(
    "
    // Grid columns sortable: init on items so each row is a movable element
    var \$gcItems = \$('.js-gc-sortable .list-draggable');
    if (\$gcItems.length && typeof \$gcItems.arrangeable === 'function') {
        \$gcItems.arrangeable({ dragSelector: '.drag-area' });
    }
    "
);
?>

<?php
$this->registerJs(
    "
    if (document.getElementById('instruction-content')) {
        \$('#instruction-content').trumbowyg({
            lang: 'ru',
            autogrow: true,
            btns: [
                ['undo', 'redo'],
                ['removeformat'],
                ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
                ['unorderedList', 'orderedList'],
                ['strong', 'em', 'del'],
                ['link'],
                ['formatting'],
                ['horizontalRule'],
                ['viewHTML'],
                ['fullscreen']
            ]
        });
    }
    "
);
?>

<?php /* Панель свойств (fixed, вне сетки форм) */ ?>
<div id="canvas-props-panel" class="canvas-props-panel">
    <div class="canvas-props-header">
        <h5 style="margin:0;font-weight:700;font-size:15px;">
            <i class="fas fa-sliders-h me-2 text-secondary"></i>Настройки поля
        </h5>
        <button type="button" class="btn-close" onclick="canvasCloseProps()"></button>
    </div>
    <div class="canvas-props-body">
        <input type="hidden" id="prop-id">

        <?php /* Ширина — общее для всех */ ?>
        <div class="prop-section">
            <h6><i class="fas fa-columns me-2"></i>Ширина в строке</h6>
            <select class="form-select form-select-sm" id="prop-width" onchange="canvasUpdateField()">
                <option value="100">100% (вся ширина)</option>
                <option value="50">50% (2 колонки)</option>
                <option value="33">33% (3 колонки)</option>
                <option value="25">25% (4 колонки)</option>
            </select>
        </div>

        <?php /* Настройки поля БД */ ?>
        <div id="props-db-group">
            <div class="mb-16">
                <label class="body-title mb-10" id="prop-db-name-label" style="font-size:12px;color:#64748b;text-transform:uppercase;">
                    Системное имя
                </label>
                <input type="text" class="form-control form-control-sm bg-light" id="prop-db-name" readonly>
            </div>

            <div class="mb-16">
                <label class="body-title mb-10">Лейбл (заголовок) <span class="tf-color-1">*</span></label>
                <input type="text" class="form-control form-control-sm" id="prop-label" oninput="canvasUpdateField()">
            </div>

            <div class="mb-16">
                <label class="body-title mb-10">Подсказка (Hint)</label>
                <input type="text" class="form-control form-control-sm" id="prop-hint"
                       placeholder="Отобразится под полем" oninput="canvasUpdateField()">
            </div>

            <div class="mb-16">
                <label class="body-title mb-10">Тип поля</label>
                <select class="form-select form-select-sm" id="prop-type"
                        onchange="canvasUpdateField(); canvasToggleSelectOpts();">
                    <option value="text">🔤 Строка</option>
                    <option value="textarea">📝 Текст</option>
                    <option value="html">📄 HTML-редактор</option>
                    <option value="visual">🎨 Визуальный редактор</option>
                    <option value="select">📋 Выпадающий список</option>
                    <option value="file">🖼 Файл / Изображение</option>
                    <option value="date">📅 Дата / Время</option>
                    <option value="posted">✅ Чекбокс</option>
                    <option value="number">🔢 Число</option>
                    <option value="hidden">🔒 Скрытое поле</option>
                    <option value="user">👤 Пользователь (User ID)</option>
                </select>
            </div>

            <div id="props-select-group" class="prop-section" style="display:none;background:#fffbeb;border-color:#fde68a;">
                <h6 style="color:#92400e;">Настройки Select</h6>
                <div class="mb-10">
                    <div class="flex gap10 mb-10">
                        <input class="total-checkbox" type="checkbox" id="prop-sel-multiple" onchange="canvasUpdateField()">
                        <label for="prop-sel-multiple" class="body-text">Множественный выбор</label>
                    </div>
                </div>
                <div class="mb-10">
                    <label class="body-title mb-10" style="font-size:12px;">Источник данных</label>
                    <select class="form-select form-select-sm mb-8" id="prop-sel-source-type" onchange="canvasUpdateField()">
                        <option value="method">Статический метод модели</option>
                        <option value="entity">Связь (ActiveQuery)</option>
                    </select>
                    <select class="form-select form-select-sm" id="prop-sel-source-val" onchange="canvasUpdateField()">
                        <option value="">--- выберите метод ---</option>
                    </select>
                </div>
                <div>
                    <label class="body-title mb-10" style="font-size:12px;">Метод для сохранения</label>
                    <select class="form-select form-select-sm" id="prop-sel-save-method" onchange="canvasUpdateField()">
                        <option value="">--- не нужен ---</option>
                    </select>
                </div>
            </div>

            <div class="flex gap10 mb-14">
                <input class="total-checkbox" type="checkbox" id="prop-required" onchange="canvasUpdateField()">
                <label for="prop-required" class="body-text">Обязательное поле (Required)</label>
            </div>
            <div class="flex gap10 mb-14">
                <input class="total-checkbox" type="checkbox" id="prop-readonly" onchange="canvasUpdateField()">
                <label for="prop-readonly" class="body-text">Только для чтения (Readonly)</label>
            </div>

            <div id="props-date-group" class="prop-section mb-14"
                 style="display:none;background:#f0fdf4;border-color:#86efac;">
                <h6 style="color:#166534;">
                    <i class="fas fa-clock me-2"></i>Настройки даты
                </h6>
                <div class="flex gap10">
                    <input class="total-checkbox" type="checkbox"
                           id="prop-with-time" onchange="canvasUpdateField()">
                    <label for="prop-with-time" class="body-text">
                        Показывать время (withTime)
                    </label>
                </div>
            </div>

            <div id="props-file-group" class="prop-section mb-14"
                 style="display:none;background:#eff6ff;border-color:#93c5fd;">
                <h6 style="color:#1e40af;">
                    <i class="fas fa-images me-2"></i>Настройки файла
                </h6>
                <div class="flex gap10">
                    <input class="total-checkbox" type="checkbox"
                           id="prop-file-multiple" onchange="canvasUpdateField()">
                    <label for="prop-file-multiple" class="body-text">
                        Множественная загрузка
                    </label>
                </div>
            </div>

            <div class="prop-section">
                <h6><i class="fas fa-users me-2"></i>Видимость (Роли)</h6>
                <p class="body-text" style="font-size:13px;margin-bottom:10px;">
                    Если ничего не выбрано — поле видно всем.
                </p>
                <div class="canvas-roles-grid" id="canvas-roles-grid"></div>
            </div>
        </div>

        <?php /* Настройки контентных блоков */ ?>
        <div id="props-content-group" style="display:none;">
            <div id="props-content-header-opts" class="mb-16">
                <label class="body-title mb-10">Тип заголовка</label>
                <select class="form-select form-select-sm" id="prop-content-tag" onchange="canvasUpdateField()">
                    <option value="h2">H2 (секция)</option>
                    <option value="h3">H3 (подсекция)</option>
                    <option value="h4">H4</option>
                    <option value="h1">H1 (главный)</option>
                </select>
            </div>
            <div id="props-content-text-opts" class="mb-16">
                <label class="body-title mb-10">Текст элемента</label>
                <textarea class="form-control form-control-sm" id="prop-content-text"
                          rows="3" oninput="canvasUpdateField()"></textarea>
            </div>
            <div id="props-content-divider-note" class="block-warning type-main w-full" style="display:none;">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Разделитель не имеет текстовых настроек. Выберите только ширину выше.</div>
            </div>
        </div>
    </div>
    <div class="canvas-props-footer">
        <button type="button" class="tf-button w-100" onclick="canvasCloseProps()">Готово</button>
    </div>
</div>

<div class="bot">
    <div></div>
    <button class="tf-button w208 js-check-to-save" type="submit">Сохранить</button>
</div>
