<?php

use Mitisk\Yii2Admin\components\cron\CronExpression;
use Mitisk\Yii2Admin\models\CronLog;
use Mitisk\Yii2Admin\models\CronTask;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $task CronTask */
/* @var $logs CronLog[] */

$isNew = $task->isNewRecord;
$this->title = $isNew ? 'Новая задача' : 'Задача «' . $task->name . '»';
$this->params['breadcrumbs'][] = ['label' => 'Планировщик', 'url' => ['index']];
$this->params['breadcrumbs'][] = $isNew ? 'Новая задача' : 'Задача';

require __DIR__ . '/_styles.php';

$formatter = Yii::$app->formatter;
$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken);
$presets = [
    '* * * * *' => 'Каждую минуту',
    '*/5 * * * *' => 'Каждые 5 минут',
    '*/15 * * * *' => 'Каждые 15 минут',
    '0 * * * *' => 'Каждый час',
    '0 3 * * *' => 'Каждый день в 03:00',
    '0 9 * * 1-5' => 'По будням в 09:00',
    '0 4 * * 1' => 'По понедельникам в 04:00',
    '0 5 1 * *' => '1-го числа каждого месяца в 05:00',
];
$schedule = trim((string) $task->schedule);
$statusLabels = [
    CronTask::STATUS_OK => ['Успешно', 'ok'],
    CronTask::STATUS_FAIL => ['Ошибка', 'fail'],
    CronTask::STATUS_TIMEOUT => ['Не уложилась во время', 'fail'],
];
$next = $isNew || !$task->enabled ? null : CronExpression::nextRun($schedule, time());
?>
<div class="cr">
    <div class="cr-form">
        <?php if ($task->isFromCode()) : ?>
        <div class="cr-card">
            <div class="cr-card__title">Задача из кода проекта</div>
            <p class="cr-text">Расписание, команду и включение задаёт код проекта (настройка <code>cronTasks</code> модуля админки) — они меняются в коде и выкладкой, а не здесь. Отсюда задачу можно запустить вручную и посмотреть её журнал.</p>
            <dl class="cr-readonly">
                <dt>Название</dt><dd><?= Html::encode($task->name) ?></dd>
                <dt>Команда</dt><dd><code>php yii <?= Html::encode($task->command) ?></code></dd>
                <dt>Расписание</dt><dd><?= Html::encode(CronExpression::describe($schedule)) ?><?php if (CronExpression::describe($schedule) !== $schedule) : ?> <span class="cr-muted">· <?= Html::encode($schedule) ?></span><?php endif; ?></dd>
                <dt>Лимит времени</dt><dd><?= (int) $task->timeout > 0 ? (int) $task->timeout . ' с' : 'без лимита' ?></dd>
                <dt>Журнал</dt><dd><?= $task->log_mode === CronTask::LOG_FAILURES ? 'ошибки и последний успешный запуск' : 'все запуски' ?></dd>
                <dt>Ключ</dt><dd><code><?= Html::encode($task->code_key) ?></code></dd>
            </dl>
            <?php if ($next !== null) : ?>
                <div class="cr-schedule-read">Ближайший запуск: <?= Html::encode($formatter->asDatetime($next, 'dd.MM.yyyy HH:mm')) ?> (<?= Html::encode($formatter->asRelativeTime($next)) ?>)</div>
            <?php endif; ?>
            <div class="cr-actions" style="margin-top:18px;">
                <a class="cr-link" href="<?= Url::to(['index']) ?>">← К списку задач</a>
            </div>
        </div>
        <?php else : ?>
        <form method="post" class="cr-card" id="cr-form">
            <?= $csrf ?>

            <label class="cr-field">
                <span class="cr-field__label">Название <span class="tf-color-1">*</span></span>
                <input type="text" name="CronTask[name]" maxlength="255" value="<?= Html::encode($task->name) ?>" placeholder="Например: Очистка кэша по ночам" autocomplete="off">
                <span class="cr-error"><?= Html::encode($task->getFirstError('name')) ?></span>
            </label>

            <div class="cr-field">
                <label class="cr-field__label" for="cr-command">Команда <span class="tf-color-1">*</span></label>
                <div class="cr-prefix">
                    <span>php yii</span>
                    <input type="text" class="cr-mono" id="cr-command" name="CronTask[command]" maxlength="500" value="<?= Html::encode($task->command) ?>" placeholder="cache/flush-all" autocomplete="off" spellcheck="false">
                </div>
                <span class="cr-error"><?= Html::encode($task->getFirstError('command')) ?></span>
                <span class="cr-hint">Консольная команда проекта вида «контроллер/действие» и её аргументы через пробел, например <code>catalog/reindex --force=1</code>. Список команд — <code>php yii help</code> на сервере.</span>
            </div>

            <div class="cr-field">
                <label class="cr-field__label" for="cr-preset">Расписание <span class="tf-color-1">*</span></label>
                <select id="cr-preset" aria-label="Готовое расписание">
                    <?php foreach ($presets as $expression => $label) : ?>
                        <option value="<?= Html::encode($expression) ?>"<?= $expression === $schedule ? ' selected' : '' ?>><?= Html::encode($label) ?></option>
                    <?php endforeach; ?>
                    <option value=""<?= isset($presets[$schedule]) ? '' : ' selected' ?>>Своё расписание…</option>
                </select>
                <div id="cr-custom"<?= isset($presets[$schedule]) ? ' hidden' : '' ?> style="margin-top:10px;">
                    <input type="text" class="cr-mono" id="cr-schedule" name="CronTask[schedule]" maxlength="100" value="<?= Html::encode($schedule) ?>" placeholder="минута час день месяц день_недели" autocomplete="off" spellcheck="false" aria-label="Расписание в формате cron">
                    <span class="cr-hint">Пять полей cron: минута, час, день месяца, месяц, день недели (0 и 7 — воскресенье). <code>*/10</code> — каждые 10, <code>1-5</code> — диапазон, <code>0,30</code> — список.</span>
                </div>
                <span class="cr-error"><?= Html::encode($task->getFirstError('schedule')) ?></span>
                <div class="cr-schedule-read" id="cr-schedule-read"<?= $next === null ? ' hidden' : '' ?>>
                    <?php if ($next !== null) : ?>
                        Ближайший запуск: <?= Html::encode($formatter->asDatetime($next, 'dd.MM.yyyy HH:mm')) ?> (<?= Html::encode($formatter->asRelativeTime($next)) ?>)
                    <?php endif; ?>
                </div>
            </div>

            <label class="cr-field" style="max-width:260px;">
                <span class="cr-field__label">Лимит времени, секунд</span>
                <input type="number" name="CronTask[timeout]" min="0" max="86400" step="1" value="<?= (int) $task->timeout ?>">
                <span class="cr-error"><?= Html::encode($task->getFirstError('timeout')) ?></span>
                <span class="cr-hint">Задача, которая не уложилась, останавливается. 0 — без лимита.</span>
            </label>

            <label class="cr-field" style="max-width:360px;">
                <span class="cr-field__label">Журнал</span>
                <select name="CronTask[log_mode]">
                    <option value="<?= CronTask::LOG_ALL ?>"<?= $task->log_mode !== CronTask::LOG_FAILURES ? ' selected' : '' ?>>Все запуски</option>
                    <option value="<?= CronTask::LOG_FAILURES ?>"<?= $task->log_mode === CronTask::LOG_FAILURES ? ' selected' : '' ?>>Ошибки и последний успешный запуск</option>
                </select>
                <span class="cr-hint">Для частых задач (каждую минуту) удобнее второе: ошибки не теряются среди сотен успешных запусков.</span>
            </label>

            <input type="hidden" name="CronTask[enabled]" value="0">
            <label class="cr-check">
                <input type="checkbox" name="CronTask[enabled]" value="1"<?= $task->enabled ? ' checked' : '' ?>>
                <span>Запускать по расписанию</span>
            </label>

            <div class="cr-actions">
                <button type="submit" class="tf-button"><?= $isNew ? 'Добавить задачу' : 'Сохранить' ?></button>
                <a class="cr-link" href="<?= Url::to(['index']) ?>">Отмена</a>
            </div>
        </form>
        <?php endif; ?>

        <?php if (!$isNew) : ?>
            <aside class="cr-side">
                <div class="cr-card">
                    <div class="cr-card__title">Запустить сейчас</div>
                    <p class="cr-text">Диспетчер выполнит задачу при ближайшем запуске — в течение минуты, даже если она выключена.</p>
                    <form method="post" action="<?= Url::to(['run-now', 'id' => $task->id]) ?>">
                        <?= $csrf ?>
                        <input type="hidden" name="back" value="form">
                        <button type="submit" class="cr-btn"<?= $task->run_requested_at !== null || $task->running_since !== null ? ' disabled' : '' ?>>
                            <i class="icon-play" aria-hidden="true"></i>
                            <?= $task->running_since !== null ? 'Выполняется…' : ($task->run_requested_at !== null ? 'В очереди' : 'Запустить') ?>
                        </button>
                    </form>
                </div>
                <?php if (!$task->isFromCode()) : ?>
                <div class="cr-card">
                    <div class="cr-card__title">Удалить задачу</div>
                    <p class="cr-text">Вместе с задачей удалится её журнал.</p>
                    <form method="post" action="<?= Url::to(['delete', 'id' => $task->id]) ?>">
                        <?= $csrf ?>
                        <details>
                            <summary class="cr-btn cr-btn--danger">Удалить…</summary>
                            <button type="submit" class="cr-btn cr-btn--danger" style="margin-top:10px;">Да, удалить «<?= Html::encode($task->name) ?>»</button>
                        </details>
                    </form>
                </div>
                <?php endif; ?>
            </aside>
        <?php endif; ?>
    </div>

    <?php if (!$isNew) : ?>
        <div class="cr-card cr-log">
            <div class="cr-log__head">
                <div>
                    <div class="cr-card__title">Журнал запусков</div>
                    <div class="cr-muted">Последние <?= count($logs) ?>. Старые записи удаляются сами — сроки в «Настройки → Планировщик».</div>
                </div>
                <?php if ($logs !== []) : ?>
                    <form method="post" action="<?= Url::to(['clear-log', 'id' => $task->id]) ?>">
                        <?= $csrf ?>
                        <button type="submit" class="cr-btn">Очистить журнал</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($logs === []) : ?>
                <p class="cr-text">Задача ещё не запускалась.</p>
            <?php endif; ?>
            <?php foreach ($logs as $log) : ?>
                <?php $status = $statusLabels[$log->status] ?? [$log->status, 'fail']; ?>
                <details class="cr-run">
                    <summary>
                        <span class="cr-run__time"><?= Html::encode($formatter->asDatetime($log->started_at, 'dd.MM.yyyy HH:mm:ss')) ?></span>
                        <span class="cr-chip cr-chip--<?= $status[1] ?>"><?= Html::encode($status[0]) ?></span>
                        <span class="cr-muted"><?= number_format((int) $log->duration_ms / 1000, 1, ',', ' ') ?> с</span>
                        <?php if ($log->source === CronLog::SOURCE_MANUAL) : ?>
                            <span class="cr-chip cr-chip--manual">вручную</span>
                        <?php endif; ?>
                        <?php if ($log->exit_code !== null && (int) $log->exit_code !== 0) : ?>
                            <span class="cr-muted">код выхода <?= (int) $log->exit_code ?></span>
                        <?php endif; ?>
                        <span class="cr-muted"><?= trim((string) $log->output) === '' ? 'без вывода' : 'показать вывод' ?></span>
                    </summary>
                    <?php if (trim((string) $log->output) !== '') : ?>
                        <pre class="cr-run__output"><?= Html::encode($log->output) ?></pre>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
$this->registerJs('(function () {
    var preset = document.getElementById("cr-preset");
    var custom = document.getElementById("cr-custom");
    var schedule = document.getElementById("cr-schedule");
    var read = document.getElementById("cr-schedule-read");
    if (!preset || !schedule) { return; }
    preset.addEventListener("change", function () {
        if (preset.value) { schedule.value = preset.value; }
        custom.hidden = preset.value !== "";
        if (read) { read.hidden = true; }
        if (!preset.value) { schedule.focus(); }
    });
    schedule.addEventListener("input", function () { if (read) { read.hidden = true; } });
})();');
