<?php
/**
 * Редактор шаблона письма: текст слева, живой предпросмотр в макете и тестовая отправка справа.
 * Скрипт и стили — assets/src/admin/email-template.js и .css (префикс et-).
 *
 * @var yii\web\View $this
 * @var Mitisk\Yii2Admin\models\EmailTemplate $model
 * @var Mitisk\Yii2Admin\components\mail\MailEventInterface|null $event событие проекта, если шаблон — его письмо
 * @var array<string, array<string, mixed>> $rows переменные (TemplateParams::rows)
 * @var array{email: string, name: string} $sender
 * @var bool $smtpReady
 * @var bool $hasLayout
 * @var string[] $adminEmails
 */

use Mitisk\Yii2Admin\assets\EmailTemplateAsset;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

EmailTemplateAsset::register($this);

$isNew = $model->isNewRecord;
$settingsUrl = Url::to(['/admin/settings/', 'modelName' => 'Mitisk\Yii2Admin\models\MailTemplate']);
$config = [
    'previewUrl' => Url::to(['preview']),
    'testUrl' => Url::to(['test-send']),
    'event' => $event !== null,
    'defaults' => $event !== null
        ? ['subject' => $event->defaultSubject(), 'body' => $event->defaultBody()]
        : null,
];
$error = static fn (string $attribute): string => Html::error($model, $attribute, ['class' => 'et-error']);

/** Строка переменной; та же разметка — в шаблоне для переменных, набранных в тексте (email-template.js). */
$varRow = static function (string $name, array $row) use ($model): string {
    $base = Html::getInputName($model, 'params') . '[' . $name . ']';
    $flag = static function (string $key, string $label, string $title, bool $checked, bool $locked) use ($base): string {
        $input = $locked
            ? Html::hiddenInput($base . '[' . $key . ']', '1') . Html::checkbox('', true, ['disabled' => true])
            : Html::hiddenInput($base . '[' . $key . ']', '0')
                . Html::checkbox($base . '[' . $key . ']', $checked, ['value' => '1', 'data-et-flag' => $key]);

        return Html::tag('label', $input . ' ' . $label, [
            'class' => 'et-flag' . ($locked ? ' is-locked' : ''),
            'title' => $locked ? 'Задано в коде сайта и здесь не отключается' : $title,
        ]);
    };

    return Html::tag(
        'div',
        Html::tag(
            'div',
            Html::button(Html::encode('{{' . $name . '}}'), [
                'class' => 'et-var__name',
                'data-et-insert' => $name,
                'title' => 'Вставить в письмо — туда, где стоит курсор',
            ])
            . Html::tag('span', '', ['class' => 'et-var__state', 'data-et-state' => true]),
            ['class' => 'et-var__head']
        )
        . Html::textInput($base . '[desc]', (string) $row['desc'], [
            'class' => 'et-input',
            'placeholder' => 'Что сюда подставится',
            'aria-label' => 'Описание переменной ' . $name,
            'maxlength' => 255,
            'data-et-desc' => true,
        ])
        . Html::textInput($base . '[sample]', (string) $row['sample'], [
            'class' => 'et-input',
            'placeholder' => 'Пример для предпросмотра',
            'aria-label' => 'Пример значения переменной ' . $name,
            'data-et-sample' => true,
        ])
        . Html::tag(
            'div',
            $flag('required', 'Обязательна', 'Без значения письмо не отправится', $row['required'] === '1', (bool) $row['lockRequired'])
            . $flag('html', 'HTML', 'Значение вставляется как разметка: ссылка, кнопка. Без отметки — как обычный текст', $row['html'] === '1', (bool) $row['lockHtml'])
            . Html::button('<i class="icon-x" aria-hidden="true"></i>', [
                'class' => 'et-var__remove',
                'data-et-remove' => true,
                'title' => 'Убрать переменную',
                'aria-label' => 'Убрать переменную ' . $name,
            ]),
            ['class' => 'et-var__flags']
        ),
        [
            'class' => 'et-var',
            'data-et-var' => $name,
            'data-code' => $row['code'] ? '1' : '0',
            'data-html-locked' => $row['lockHtml'] ? '1' : '0',
        ]
    );
};
?>
<div class="et" data-et data-config="<?= Html::encode(Json::encode($config)) ?>">

    <div class="et-top">
        <a class="et-link" href="<?= Url::to(['index']) ?>"><i class="icon-arrow-left" aria-hidden="true"></i> Все шаблоны</a>
        <span class="et-top__space"></span>
        <a class="et-btn" href="<?= Url::to(['layout']) ?>" title="Общая рамка всех писем: шапка, подвал, цвета"><i class="icon-layout" aria-hidden="true"></i> Макет писем</a>
        <a class="et-btn" href="<?= $settingsUrl ?>" title="Адрес и пароль почтового сервера, имя отправителя"><i class="icon-settings" aria-hidden="true"></i> Почтовый сервер</a>
    </div>

    <?php if (!$smtpReady) : ?>
        <div class="et-alert" role="status">
            <strong>Почтовый сервер не настроен.</strong>
            Письма с сайта сейчас не уходят, тестовое тоже не отправится.
            <a href="<?= $settingsUrl ?>">Настроить</a>
        </div>
    <?php endif; ?>

    <?= Html::beginForm('', 'post', ['id' => 'email-template-form', 'class' => 'et-grid', 'novalidate' => true]) ?>

        <div class="et-main">

            <section class="et-card">
                <div class="et-head">
                    <div class="et-field et-field--grow">
                        <label class="et-label" for="et-name">Название</label>
                        <?= Html::activeTextInput($model, 'name', ['id' => 'et-name', 'class' => 'et-input et-input--big', 'maxlength' => true, 'placeholder' => 'Например: Заказ оплачен']) ?>
                        <p class="et-hint">Видно только в админке — чтобы найти письмо в списке.</p>
                        <?= $error('name') ?>
                    </div>
                    <label class="et-switch" title="Выключенное письмо сайт не отправляет">
                        <?= Html::hiddenInput(Html::getInputName($model, 'active'), '0') ?>
                        <?= Html::checkbox(Html::getInputName($model, 'active'), (bool) $model->active, ['value' => '1', 'data-et-active' => true]) ?>
                        <span class="et-switch__track" aria-hidden="true"></span>
                        <span class="et-switch__text" data-et-active-text><?= $model->active ? 'Письмо отправляется' : 'Выключено' ?></span>
                    </label>
                </div>

                <div class="et-field et-code">
                    <label class="et-label" for="et-slug">Код шаблона</label>
                    <div class="et-code__row">
                        <?= Html::activeTextInput($model, 'slug', [
                            'id' => 'et-slug',
                            'class' => 'et-input et-input--mono',
                            'maxlength' => true,
                            'readonly' => !$isNew,
                            'placeholder' => 'order_paid',
                            'data-et-slug' => true,
                        ]) ?>
                        <?php if (!$isNew && $event === null) : ?>
                            <button type="button" class="et-btn" data-et-slug-unlock>Изменить</button>
                        <?php endif; ?>
                    </div>
                    <p class="et-hint" data-et-slug-hint>
                        <?php if ($event !== null) : ?>
                            Это письмо отправляет сайт («<?= Html::encode($event->label()) ?>»). Код задан в коде сайта и здесь не меняется.
                        <?php elseif ($isNew) : ?>
                            По этому коду сайт находит письмо. Латинские буквы, цифры, дефис и подчёркивание.
                        <?php else : ?>
                            По этому коду сайт находит письмо. Поменяете код — письмо перестанет отправляться, пока код не поменяют и на сайте.
                        <?php endif; ?>
                    </p>
                    <?= $error('slug') ?>
                </div>
            </section>

            <section class="et-card">
                <div class="et-card__head">
                    <h2 class="et-title">Письмо</h2>
                    <?php if ($event !== null) : ?>
                        <button type="button" class="et-btn" data-et-reset title="Подставит в редактор исходные тему и текст из кода сайта. Сохранится только после «Сохранить»">
                            <i class="icon-rotate-ccw" aria-hidden="true"></i> Вернуть исходный текст
                        </button>
                    <?php endif; ?>
                </div>

                <div class="et-field">
                    <div class="et-label-row">
                        <label class="et-label" for="et-subject">Тема</label>
                        <span class="et-counter" data-et-counter></span>
                    </div>
                    <?= Html::activeTextInput($model, 'subject', ['id' => 'et-subject', 'class' => 'et-input', 'maxlength' => true, 'data-et-subject' => true, 'placeholder' => 'Заказ №{{ID}} оплачен']) ?>
                    <?= $error('subject') ?>
                </div>

                <div class="et-field">
                    <label class="et-label" for="et-body">Текст письма</label>
                    <div class="et-chips" data-et-chips aria-label="Вставить переменную"></div>
                    <?= Html::activeTextarea($model, 'body', ['id' => 'et-body', 'rows' => 14, 'data-et-body' => true]) ?>
                    <?= $error('body') ?>
                </div>
            </section>

            <section class="et-card">
                <h2 class="et-title">Переменные</h2>
                <p class="et-hint et-hint--lead">
                    Вместо <code>{{ИМЯ}}</code> сайт подставит своё значение: имя клиента, номер заказа, ссылку.
                    Нажмите на переменную, чтобы вставить её в тему или текст. Пример нужен только для предпросмотра и тестового письма.
                </p>
                <div class="et-vars" data-et-vars>
                    <?php foreach ($rows as $name => $row) : ?>
                        <?= $varRow((string) $name, $row) ?>
                    <?php endforeach; ?>
                </div>
                <p class="et-none" data-et-vars-empty<?= $rows === [] ? '' : ' hidden' ?>>
                    Переменных пока нет. Наберите в теме или тексте, например, <code>{{NAME}}</code> — она появится здесь.
                </p>
                <template data-et-var-template><?= $varRow('__NAME__', ['desc' => '', 'sample' => '', 'required' => '0', 'html' => '0', 'code' => false, 'lockRequired' => false, 'lockHtml' => false]) ?></template>
            </section>
        </div>

        <aside class="et-side">
            <section class="et-card et-preview">
                <div class="et-card__head">
                    <h2 class="et-title">Как увидит получатель</h2>
                    <div class="et-seg" role="group" aria-label="Ширина экрана">
                        <button type="button" class="is-active" data-et-device="desktop" title="Компьютер"><i class="icon-monitor" aria-hidden="true"></i></button>
                        <button type="button" data-et-device="phone" title="Телефон"><i class="icon-smartphone" aria-hidden="true"></i></button>
                    </div>
                </div>
                <dl class="et-envelope">
                    <div><dt>От кого</dt><dd><?= Html::encode($sender['name']) ?> <span>&lt;<?= Html::encode($sender['email']) ?>&gt;</span></dd></div>
                    <div><dt>Тема</dt><dd data-et-preview-subject>—</dd></div>
                </dl>
                <div class="et-frame" data-et-frame>
                    <iframe title="Предпросмотр письма" sandbox="allow-same-origin" data-et-iframe></iframe>
                </div>
                <p class="et-hint" data-et-preview-note>
                    <?php if (!$hasLayout) : ?>
                        Общий макет писем не задан — письмо уйдёт без шапки и подвала. <a href="<?= Url::to(['layout']) ?>">Настроить макет</a>
                    <?php else : ?>
                        Показано с общим макетом и примерами вместо переменных.
                    <?php endif; ?>
                </p>
            </section>

            <section class="et-card et-test">
                <h2 class="et-title">Тестовое письмо</h2>
                <p class="et-hint et-hint--lead">Уйдёт то, что сейчас в редакторе, с примерами вместо переменных. Сохранять не обязательно.</p>
                <div class="et-test__row">
                    <input type="email" class="et-input" placeholder="name@example.com" aria-label="Куда отправить"
                           list="et-admin-emails" value="<?= Html::encode($adminEmails[0] ?? '') ?>" data-et-test-email>
                    <button type="button" class="et-btn et-btn--primary" data-et-test-send>Отправить</button>
                </div>
                <datalist id="et-admin-emails">
                    <?php foreach ($adminEmails as $email) : ?>
                        <option value="<?= Html::encode($email) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="et-result" data-et-test-result role="status" hidden></p>
            </section>
        </aside>

        <div class="et-bar">
            <span class="et-bar__state" data-et-dirty>
                <?php if ($isNew) : ?>
                    Новый шаблон
                <?php elseif ($model->updated_at) : ?>
                    Сохранён <?= date('d.m.Y H:i', (int) $model->updated_at) ?>
                <?php endif; ?>
            </span>
            <a class="et-btn" href="<?= Url::to(['index']) ?>">К списку</a>
            <?= Html::submitButton($isNew ? 'Создать шаблон' : 'Сохранить', ['class' => 'tf-button et-save']) ?>
        </div>

    <?= Html::endForm() ?>
</div>
