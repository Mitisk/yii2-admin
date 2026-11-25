<?php

use Mitisk\Yii2Admin\components\cron\CronExpression;
use Mitisk\Yii2Admin\models\CronTask;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $tasks CronTask[] */
/* @var $next array<int, int|null> */
/* @var $heartbeat int|null */
/* @var $alive bool */
/* @var $crontabLine string */
/* @var $failedToday int */

$this->title = 'Планировщик';
$this->params['breadcrumbs'][] = $this->title;
$this->params['recordCount'] = count($tasks);

require __DIR__ . '/_styles.php';

$formatter = Yii::$app->formatter;
$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken);
$statusLabels = [
    CronTask::STATUS_OK => ['Успешно', 'ok'],
    CronTask::STATUS_FAIL => ['Ошибка', 'fail'],
    CronTask::STATUS_TIMEOUT => ['Не уложилась во время', 'fail'],
];
?>
<div class="cr">

    <div class="cr-top">
        <div class="cr-state cr-state--<?= $alive ? 'ok' : 'off' ?>">
            <span class="cr-state__dot" aria-hidden="true"></span>
            <div>
                <div class="cr-state__title"><?= $alive ? 'Диспетчер работает' : ($heartbeat === null ? 'Диспетчер не подключён' : 'Диспетчер молчит') ?></div>
                <div class="cr-state__text">
                    <?php if ($heartbeat !== null) : ?>
                        Последний запуск — <?= Html::encode($formatter->asRelativeTime($heartbeat)) ?>.
                    <?php else : ?>
                        Задачи не запускаются, пока на сервере нет строки в crontab.
                    <?php endif; ?>
                    <?php if ($failedToday > 0) : ?>
                        За сутки неудачных запусков: <strong><?= $failedToday ?></strong>.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <a class="tf-button" href="<?= Url::to(['create']) ?>"><i class="icon-plus"></i> Новая задача</a>
    </div>

    <?php if (!$alive) : ?>
        <div class="cr-card cr-setup">
            <div class="cr-card__title">Как подключить планировщик</div>
            <p class="cr-text">На сервере нужна одна строка в crontab (команда <code>crontab -e</code>). Она раз в минуту запускает диспетчер, а что и когда выполнять, вы решаете здесь — править crontab больше не придётся.</p>
            <pre class="cr-code" id="cr-crontab"><?= Html::encode($crontabLine) ?></pre>
            <p class="cr-text cr-text--muted">Путь к проекту подставлен автоматически — проверьте его. В консольном конфиге проекта должен быть подключён модуль <code>admin</code>.</p>
        </div>
    <?php endif; ?>

    <?php if ($tasks === []) : ?>
        <div class="cr-card cr-empty">
            <div class="cr-card__title">Задач пока нет</div>
            <p class="cr-text">Задача — это консольная команда проекта, которая выполняется по расписанию: очистка кэша, пересчёт, рассылка. Например, <code>cache/flush-all</code> каждый день в 03:00.</p>
            <a class="tf-button style-1" href="<?= Url::to(['create']) ?>">Добавить первую задачу</a>
        </div>
    <?php else : ?>
        <div class="cr-list">
            <?php foreach ($tasks as $task) : ?>
                <?php
                $status = $statusLabels[$task->last_status] ?? null;
                $running = $task->running_since !== null;
                ?>
                <div class="cr-card cr-task<?= $task->enabled ? '' : ' is-off' ?>">
                    <div class="cr-task__main">
                        <a class="cr-task__name" href="<?= Url::to(['update', 'id' => $task->id]) ?>"><?= Html::encode($task->name) ?></a>
                        <code class="cr-task__command">php yii <?= Html::encode($task->command) ?></code>
                        <div class="cr-task__schedule">
                            <i class="icon-clock" aria-hidden="true"></i>
                            <?= Html::encode(CronExpression::describe((string) $task->schedule)) ?>
                            <?php if (CronExpression::describe((string) $task->schedule) !== trim((string) $task->schedule)) : ?>
                                <span class="cr-muted">· <?= Html::encode($task->schedule) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cr-task__facts">
                        <div class="cr-fact">
                            <span class="cr-fact__label">Следующий запуск</span>
                            <span class="cr-fact__value">
                                <?php if ($task->run_requested_at !== null) : ?>
                                    в течение минуты
                                <?php elseif (!$task->enabled) : ?>
                                    выключена
                                <?php elseif ($next[$task->id] !== null) : ?>
                                    <span title="<?= Html::encode($formatter->asDatetime($next[$task->id], 'dd.MM.yyyy HH:mm')) ?>"><?= Html::encode($formatter->asRelativeTime($next[$task->id])) ?></span>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="cr-fact">
                            <span class="cr-fact__label">Последний запуск</span>
                            <span class="cr-fact__value">
                                <?php if ($running) : ?>
                                    <span class="cr-chip cr-chip--run">Выполняется</span>
                                <?php elseif ($task->last_run_at !== null && $status !== null) : ?>
                                    <span class="cr-chip cr-chip--<?= $status[1] ?>"><?= $status[0] ?></span>
                                    <span class="cr-muted" title="<?= Html::encode($formatter->asDatetime($task->last_run_at, 'dd.MM.yyyy HH:mm')) ?>">
                                        <?= Html::encode($formatter->asRelativeTime($task->last_run_at)) ?>
                                        · <?= number_format((int) $task->last_duration_ms / 1000, 1, ',', ' ') ?> с
                                    </span>
                                <?php else : ?>
                                    <span class="cr-muted">ещё не запускалась</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="cr-task__tools">
                        <form method="post" action="<?= Url::to(['run-now', 'id' => $task->id]) ?>">
                            <?= $csrf ?>
                            <button type="submit" class="cr-btn" title="Выполнить при ближайшем запуске диспетчера"<?= $task->run_requested_at !== null || $running ? ' disabled' : '' ?>>
                                <i class="icon-play" aria-hidden="true"></i> Запустить
                            </button>
                        </form>
                        <form method="post" action="<?= Url::to(['toggle', 'id' => $task->id]) ?>">
                            <?= $csrf ?>
                            <button type="submit" class="cr-btn"><?= $task->enabled ? 'Выключить' : 'Включить' ?></button>
                        </form>
                        <a class="cr-btn" href="<?= Url::to(['update', 'id' => $task->id]) ?>">Журнал и настройки</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
