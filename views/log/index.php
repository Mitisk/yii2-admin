<?php
/**
 * Журналы: «Действия» (кто и что менял) и «Ошибки сайта» (лог приложения, собранный в проблемы).
 * Стили и скрипт — assets/src/admin/log.css и log.js (префикс lg-).
 *
 * @var \yii\web\View $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 * @var array<string, string> $filters
 * @var \Mitisk\Yii2Admin\components\audit\AuditActions $actions
 * @var \Mitisk\Yii2Admin\components\audit\AuditPresenter $presenter
 * @var array<string, string> $usersList
 * @var array<string, string> $actionsList
 * @var array<string, string> $sectionsList
 * @var int $totalAll
 * @var int|string|null $oldest
 * @var bool $canClear
 * @var bool $canSystem
 */

use Mitisk\Yii2Admin\assets\LogAsset;
use Mitisk\Yii2Admin\components\audit\AuditPresenter;
use Mitisk\Yii2Admin\controllers\LogController;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;

LogAsset::register($this);

$this->title = 'Журналы';
$this->params['breadcrumbs'][] = $this->title;
$this->params['recordCount'] = $dataProvider->getTotalCount();

$months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
$today = strtotime('today');
$dayTitle = static function (int $ts) use ($months, $today): string {
    $day = strtotime('today', $ts);
    if ($day === $today) {
        return 'Сегодня';
    }
    if ($day === $today - 86400) {
        return 'Вчера';
    }

    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . (date('Y', $ts) === date('Y') ? '' : ' ' . date('Y', $ts));
};
$plural = static function (int $n, string $one, string $few, string $many): string {
    $mod10 = $n % 10;
    $mod100 = $n % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $n . ' ' . $one;
    }

    return $n . ' ' . ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) ? $few : $many);
};
/** Значение «было / стало»: пусто, коротко или под раскрытием. */
$cell = static function (array $value): string {
    if ($value['empty']) {
        return '<span class="lg-empty">пусто</span>';
    }
    $text = $value['text'];
    if (mb_strlen($text) <= AuditPresenter::LONG_VALUE && !str_contains($text, "\n")) {
        return Html::encode($text);
    }
    $short = mb_substr(strtok($text, "\n") ?: '', 0, AuditPresenter::LONG_VALUE);

    return '<details class="lg-long"><summary>' . Html::encode($short) . '… <span>показать полностью</span></summary>'
        . '<pre>' . Html::encode($text) . '</pre></details>';
};

$activeFilters = array_filter($filters, static fn (string $value): bool => $value !== '');
$periodUrl = static function (string $period) use ($filters): string {
    $params = array_filter(['period' => $period] + array_diff_key($filters, ['date_from' => 1, 'date_to' => 1, 'period' => 1]));

    return Url::to(['index'] + $params);
};
$found = $dataProvider->getTotalCount();
$models = $dataProvider->getModels();
?>
<div class="lg" data-lg>

    <?php if ($canSystem) : ?>
        <div class="lg-tabs" role="tablist">
            <button type="button" class="lg-tab is-active" role="tab" aria-selected="true" data-lg-tab="audit">
                <i class="icon-shield" aria-hidden="true"></i> Действия
            </button>
            <button type="button" class="lg-tab" role="tab" aria-selected="false" data-lg-tab="system">
                <i class="icon-alert-triangle" aria-hidden="true"></i> Ошибки сайта
                <span class="lg-tab__badge" data-lg-badge hidden></span>
            </button>
        </div>
    <?php endif; ?>

    <?php /* ─── Действия ─────────────────────────────────────────── */ ?>
    <section class="lg-pane" data-lg-pane="audit" role="tabpanel">

        <form method="get" action="<?= Url::to(['index']) ?>" class="lg-card lg-filters">
            <div class="lg-periods" role="group" aria-label="Период">
                <a class="lg-period<?= $filters['period'] === '' && $filters['date_from'] === '' && $filters['date_to'] === '' ? ' is-active' : '' ?>"
                   href="<?= $periodUrl('') ?>">Всё время</a>
                <?php foreach (LogController::PERIODS as $key => [$label]) : ?>
                    <a class="lg-period<?= $filters['period'] === (string) $key ? ' is-active' : '' ?>"
                       href="<?= $periodUrl((string) $key) ?>"><?= Html::encode($label) ?></a>
                <?php endforeach; ?>
                <span class="lg-dates">
                    <label>с <?= Html::input('date', 'date_from', $filters['date_from'], ['aria-label' => 'Дата с']) ?></label>
                    <label>по <?= Html::input('date', 'date_to', $filters['date_to'], ['aria-label' => 'Дата по']) ?></label>
                </span>
            </div>
            <div class="lg-fields">
                <?= Html::hiddenInput('period', $filters['period']) ?>
                <?= Html::textInput('search', $filters['search'], [
                    'class' => 'lg-search',
                    'placeholder' => 'Название записи, её номер или изменённое значение',
                    'aria-label' => 'Поиск',
                ]) ?>
                <?= Html::dropDownList('user_id', $filters['user_id'], ['' => 'Все пользователи'] + $usersList, ['aria-label' => 'Пользователь']) ?>
                <?= Html::dropDownList('action', $filters['action'], ['' => 'Все действия'] + $actionsList, ['aria-label' => 'Действие']) ?>
                <?= Html::dropDownList('section', $filters['section'], ['' => 'Все разделы'] + $sectionsList, ['aria-label' => 'Раздел']) ?>
                <button type="submit" class="tf-button">Найти</button>
                <?php if ($activeFilters !== []) : ?>
                    <a class="lg-reset" href="<?= Url::to(['index']) ?>">Сбросить</a>
                <?php endif; ?>
            </div>
        </form>

        <p class="lg-count">
            <?php if ($activeFilters !== []) : ?>
                Найдено: <strong><?= $found ?></strong> из <?= $totalAll ?>
            <?php elseif ($totalAll > 0) : ?>
                Всего записей: <strong><?= $totalAll ?></strong><?= $oldest ? ', с ' . date('d.m.Y', (int) $oldest) : '' ?>
            <?php endif; ?>
        </p>

        <?php if ($models === []) : ?>
            <div class="lg-card lg-none">
                <?php if ($totalAll === 0) : ?>
                    <div class="lg-none__title">Журнал пуст</div>
                    <p>Сюда попадает каждое создание, изменение и удаление в админке: кто, когда и что именно поменял.</p>
                <?php else : ?>
                    <div class="lg-none__title">Ничего не найдено</div>
                    <p>Под эти условия не подходит ни одна запись. <a href="<?= Url::to(['index']) ?>">Сбросить фильтры</a></p>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="lg-feed">
                <?php
                $currentDay = null;
                foreach ($models as $log) :
                    /** @var \Mitisk\Yii2Admin\models\AuditLog $log */
                    $day = date('Y-m-d', (int) $log->created_at);
                    if ($day !== $currentDay) :
                        $currentDay = $day; ?>
                        <h2 class="lg-day"><?= Html::encode($dayTitle((int) $log->created_at)) ?></h2>
                    <?php endif;

                    $user = $log->user;
                    $changes = $presenter->changes($log);
                    $isCreate = $log->action === 'create';
                    $url = $presenter->url($log);
                    $tone = $actions->tone($log->action);
                    $client = $presenter->client($log->user_agent);
                    ?>
                    <details class="lg-entry">
                        <summary>
                            <time class="lg-time"><?= date('H:i', (int) $log->created_at) ?></time>
                            <span class="lg-who">
                                <?php if ($user) : ?>
                                    <?= Html::img($user->getAvatar(), ['width' => 24, 'height' => 24, 'alt' => '']) ?>
                                    <?= Html::encode($user->name ?: $user->username) ?>
                                <?php elseif ($log->user_id) : ?>
                                    <span class="lg-muted">Пользователь #<?= (int) $log->user_id ?></span>
                                <?php else : ?>
                                    <span class="lg-muted">Система</span>
                                <?php endif; ?>
                            </span>
                            <span class="lg-chip lg-chip--<?= $tone ?>"><?= Html::encode($actions->label($log->action)) ?></span>
                            <span class="lg-what">
                                <span class="lg-section"><?= Html::encode($presenter->section($log->model_class)) ?></span>
                                <?php $label = Html::encode($presenter->label($log)); ?>
                                <?= $url !== null ? Html::a($label, $url, ['class' => 'lg-record']) : '<span class="lg-record">' . $label . '</span>' ?>
                            </span>
                            <span class="lg-more">
                                <?= $changes !== [] ? $plural(count($changes), 'поле', 'поля', 'полей') : 'подробнее' ?>
                                <i class="icon-chevron-down" aria-hidden="true"></i>
                            </span>
                        </summary>
                        <div class="lg-body">
                            <?php if ($changes !== []) : ?>
                                <table class="lg-diff">
                                    <thead>
                                    <tr>
                                        <th>Поле</th>
                                        <?php if ($isCreate) : ?>
                                            <th>Значение</th>
                                        <?php else : ?>
                                            <th>Было</th>
                                            <th>Стало</th>
                                        <?php endif; ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($changes as $change) : ?>
                                        <tr>
                                            <th scope="row" title="<?= Html::encode($change['attribute']) ?>"><?= Html::encode($change['field']) ?></th>
                                            <?php if (!$isCreate) : ?>
                                                <td class="lg-old"><?= $cell($change['old']) ?></td>
                                            <?php endif; ?>
                                            <td class="lg-new"><?= $cell($change['new']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php elseif ($log->action === 'delete') : ?>
                                <p class="lg-note">Запись удалена — её содержимое в журнале не хранится.</p>
                            <?php elseif ($log->action === 'update') : ?>
                                <p class="lg-note">Какие поля менялись, в журнал не записано.</p>
                            <?php endif; ?>
                            <dl class="lg-meta">
                                <div><dt>Когда</dt><dd><?= date('d.m.Y H:i:s', (int) $log->created_at) ?></dd></div>
                                <?php if ($log->ip) : ?>
                                    <div><dt>IP</dt><dd><?= Html::encode($log->ip) ?></dd></div>
                                <?php endif; ?>
                                <?php if ($client !== '') : ?>
                                    <div><dt>Браузер</dt><dd title="<?= Html::encode((string) $log->user_agent) ?>"><?= Html::encode($client) ?></dd></div>
                                <?php endif; ?>
                                <div><dt>Запись</dt><dd><?= Html::encode(StringHelper::basename($log->model_class)) ?> #<?= Html::encode($log->model_id) ?></dd></div>
                                <?php if (!$actions->isKnown($log->action)) : ?>
                                    <div><dt>Код действия</dt><dd><?= Html::encode($log->action) ?> — название задаётся настройкой модуля <code>auditActions</code></dd></div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div class="lg-pager">
                <?= \Mitisk\Yii2Admin\widgets\LinkPager::widget(['pagination' => $dataProvider->getPagination()]) ?>
            </div>
        <?php endif; ?>

        <?php if ($canClear && $totalAll > 0) : ?>
            <details class="lg-card lg-clear">
                <summary>Очистка журнала</summary>
                <?= Html::beginForm(['clear-audit'], 'post', ['class' => 'lg-clear__form']) ?>
                    <label for="lg-keep">Оставить записи за последние</label>
                    <select name="keep_days" id="lg-keep">
                        <?php foreach (LogController::KEEP_DAYS as $days) : ?>
                            <option value="<?= $days ?>"><?= $days > 0 ? $days . ' дней' : 'ничего не оставлять — удалить все' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="lg-btn lg-btn--danger"
                            data-confirm="Удалить старые записи журнала? Вернуть их будет нельзя.">Удалить остальные</button>
                <?= Html::endForm() ?>
                <p class="lg-note">Очистка сама попадает в журнал: видно, кто и когда её сделал.</p>
            </details>
        <?php endif; ?>
    </section>

    <?php /* ─── Ошибки сайта ─────────────────────────────────────── */ ?>
    <?php if ($canSystem) : ?>
        <section class="lg-pane" data-lg-pane="system" role="tabpanel" hidden
                 data-url="<?= Url::to(['system-log']) ?>">

            <div class="lg-summary" data-lg-summary aria-live="polite"></div>

            <div class="lg-card lg-toolbar">
                <div class="lg-switch" role="group" aria-label="Вид">
                    <button type="button" class="is-active" data-lg-view="groups" title="Одинаковые записи — одной строкой со счётчиком">Проблемы</button>
                    <button type="button" data-lg-view="list" title="Все записи по порядку, новые сверху">Подряд</button>
                </div>
                <select data-lg-level aria-label="Уровень">
                    <option value="">Все уровни</option>
                    <option value="error">Ошибки</option>
                    <option value="warning">Предупреждения</option>
                    <option value="info">Сообщения</option>
                </select>
                <input type="search" class="lg-search" data-lg-search placeholder="Поиск: текст ошибки, файл, адрес" aria-label="Поиск по логу: текст ошибки, файл или адрес страницы">
                <label class="lg-check" title="Страница не найдена, нет доступа, слишком частые запросы — это не поломка сайта">
                    <input type="checkbox" data-lg-noise> Скрыть ошибки посетителей
                </label>
                <button type="button" class="lg-btn" data-lg-refresh><i class="icon-refresh-ccw" aria-hidden="true"></i> Обновить</button>
            </div>

            <p class="lg-count" data-lg-count></p>
            <div class="lg-feed" data-lg-list></div>
            <div class="lg-pager">
                <button type="button" class="lg-btn" data-lg-more hidden>Показать ещё</button>
            </div>
        </section>
    <?php endif; ?>

</div>
