<?php
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $menus array<int, array{id: int, alias: string, name: string, locked: bool, items: array}> */
/* @var $rules array{roles: array, permissions: array} */
/* @var $links array<int, array{href: string, label: string}> */

$this->title = 'Меню';
$this->params['breadcrumbs'][] = $this->title;

\Mitisk\Yii2Admin\assets\MenuFormAsset::register($this);

// Иконки для быстрого выбора (Font Awesome 5, как в боковом меню); любой другой класс можно вписать вручную
$icons = ['home', 'user-friends', 'users', 'user', 'cog', 'cogs', 'box', 'boxes', 'shopping-cart', 'shopping-bag',
    'file', 'file-alt', 'folder', 'image', 'images', 'envelope', 'comment', 'comments', 'bell', 'calendar-alt',
    'chart-bar', 'chart-line', 'chart-pie', 'database', 'list', 'th-large', 'tags', 'star', 'heart',
    'map-marker-alt', 'globe', 'link', 'lock', 'shield-alt', 'key', 'wrench', 'tools', 'flask', 'newspaper',
    'bullhorn', 'credit-card', 'ruble-sign', 'gift', 'music', 'camera', 'video', 'question-circle', 'info-circle'];
?>

<div class="me-editor" id="me-editor"
     data-save-url="<?= Url::to(['/admin/menu/save']) ?>"
     data-delete-url="<?= Url::to(['/admin/menu/delete']) ?>">

    <script type="application/json" id="me-data"><?= Json::htmlEncode(['menus' => $menus]) ?></script>

    <div class="me-tabs" id="me-tabs" role="tablist" aria-label="Меню"></div>

    <div class="me-layout">
        <section class="me-card me-structure">
            <div class="me-card__head">
                <div>
                    <div class="me-card__title" id="me-menu-title"></div>
                    <p class="me-card__lead" id="me-menu-lead"></p>
                </div>
                <button type="button" class="tf-button style-1 me-add-root" id="me-add-root"><i class="icon-plus"></i> Пункт</button>
            </div>

            <div class="me-settings" id="me-settings" hidden>
                <label class="me-field">
                    <span class="me-field__label">Название меню</span>
                    <input type="text" id="me-menu-name" maxlength="255" autocomplete="off">
                </label>
                <label class="me-field">
                    <span class="me-field__label">Алиас</span>
                    <input type="text" id="me-menu-alias" maxlength="255" autocomplete="off" placeholder="footer">
                    <span class="me-hint" id="me-menu-alias-hint">Латиница, по нему меню получают в коде сайта. После сохранения не меняется.</span>
                </label>
                <button type="button" class="me-link me-link--danger" id="me-menu-delete" hidden>Удалить меню</button>
            </div>
            <div class="me-error" id="me-menu-error" hidden></div>

            <ul class="me-list" id="me-list"></ul>
            <p class="me-empty" id="me-empty" hidden>В меню пока нет пунктов. Нажмите «Пункт», чтобы добавить первый.</p>
            <p class="me-hint me-structure__hint">Перетаскивайте пункты за <i class="icon-menu"></i>, чтобы поменять порядок или вложить один в другой. Боковое меню показывает два уровня.</p>
        </section>

        <aside class="me-card me-form" id="me-form-card">
            <div class="me-form__placeholder" id="me-form-empty">
                <div class="me-card__title">Пункт меню</div>
                <p class="me-card__lead">Выберите пункт слева, чтобы изменить его, или добавьте новый.</p>
            </div>

            <form id="me-form" hidden novalidate>
                <div class="me-card__title" id="me-form-title">Пункт меню</div>

                <label class="me-field">
                    <span class="me-field__label">Название <span class="tf-color-1">*</span></span>
                    <input type="text" id="me-text" maxlength="100" autocomplete="off">
                    <span class="me-error" id="me-text-error" hidden>Введите название пункта.</span>
                </label>

                <label class="me-field">
                    <span class="me-field__label">Ссылка</span>
                    <input type="text" id="me-href" maxlength="500" autocomplete="off" list="me-links" placeholder="/admin/product/">
                    <span class="me-hint">Адрес раздела. «#» — заголовок группы без своей страницы.</span>
                </label>
                <datalist id="me-links">
                    <?php foreach ($links as $link): ?>
                        <option value="<?= Html::encode($link['href']) ?>"><?= Html::encode($link['label']) ?></option>
                    <?php endforeach; ?>
                </datalist>

                <div class="me-field" id="me-icon-field">
                    <span class="me-field__label">Иконка</span>
                    <div class="me-icon-row">
                        <span class="me-icon-preview" id="me-icon-preview" aria-hidden="true"></span>
                        <input type="text" id="me-icon" maxlength="60" autocomplete="off" placeholder="fas fa-box" aria-label="Класс иконки">
                        <button type="button" class="me-link" id="me-icon-clear">Без иконки</button>
                    </div>
                    <div class="me-icons" id="me-icons">
                        <?php foreach ($icons as $icon): ?>
                            <button type="button" class="me-icons__item" data-icon="fas fa-<?= $icon ?>" title="<?= $icon ?>" aria-label="<?= $icon ?>"><i class="fas fa-<?= $icon ?>"></i></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <label class="me-field">
                    <span class="me-field__label">Кто видит</span>
                    <select id="me-rule">
                        <option value="">Все, кто вошёл в админку</option>
                        <optgroup label="Роли">
                            <?php foreach ($rules['roles'] as $rule): ?>
                                <option value="<?= Html::encode($rule['value']) ?>"><?= Html::encode($rule['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Права">
                            <?php foreach ($rules['permissions'] as $rule): ?>
                                <option value="<?= Html::encode($rule['value']) ?>"><?= Html::encode($rule['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <span class="me-hint">Пункт скрыт от тех, у кого нет этой роли или права.</span>
                </label>

                <label class="me-check">
                    <input type="checkbox" id="me-blank">
                    <span>Открывать в новой вкладке</span>
                </label>

                <label class="me-field">
                    <span class="me-field__label">Подсказка</span>
                    <input type="text" id="me-title" maxlength="255" autocomplete="off" placeholder="Показывается при наведении">
                </label>

                <div class="me-form__actions">
                    <button type="submit" class="tf-button" id="me-form-apply">Готово</button>
                    <button type="button" class="me-link" id="me-form-cancel">Отмена</button>
                </div>
            </form>
        </aside>
    </div>

    <div class="me-actions">
        <button type="button" class="tf-button w180" id="me-save">Сохранить</button>
        <span class="me-actions__state" id="me-state" aria-live="polite"></span>
    </div>

    <?= Html::beginForm(['/admin/menu/delete'], 'post', ['id' => 'me-delete-form', 'hidden' => true]) ?>
    <?= Html::endForm() ?>
</div>
