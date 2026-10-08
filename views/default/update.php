<?php
/**
 * Страница самообновления модуля.
 *
 * @var yii\web\View                                   $this
 * @var \Mitisk\Yii2Admin\components\SelfUpdateService $service
 * @var string                                         $currentVersion
 * @var string|null                                    $latestVersion
 * @var bool                                           $hasNewVersion
 * @var string|null                                    $constraint
 * @var bool|null                                      $constraintAllows
 * @var array                                          $checks
 * @var bool                                           $canRun
 * @var array                                          $state
 * @var string                                         $log
 * @var string                                         $yiiScript
 * @var string                                         $phpPath
 */

use Mitisk\Yii2Admin\components\SelfUpdateService;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'Обновление админки';
$this->params['breadcrumbs'][] = $this->title;

$isRunning = in_array($state['status'], [SelfUpdateService::STATUS_STARTING, SelfUpdateService::STATUS_RUNNING], true);
$manualCmd = $phpPath . ' ' . $yiiScript . ' admin/update';
$cronLine = '0 4 * * * ' . $manualCmd . ' >> ' . dirname($yiiScript) . '/runtime/admin-update.log 2>&1';
?>

<div class="wg-box mb-20">
    <div class="flex items-center justify-between gap20 flex-wrap">
        <div>
            <div class="body-text" style="color:#64748b;">Установлено</div>
            <h4>v<?= Html::encode($currentVersion) ?></h4>
        </div>
        <div>
            <div class="body-text" style="color:#64748b;">Последний релиз на GitHub</div>
            <h4><?= $latestVersion ? 'v' . Html::encode($latestVersion) : 'нет данных' ?></h4>
        </div>
        <div>
            <div class="body-text" style="color:#64748b;">Ограничение в composer.json</div>
            <h4><code><?= Html::encode($constraint ?? '—') ?></code></h4>
        </div>
        <div>
            <?php if ($hasNewVersion) : ?>
                <span class="block-pending">Доступно обновление</span>
            <?php elseif ($latestVersion) : ?>
                <span class="block-available">Установлена последняя версия</span>
            <?php else : ?>
                <span class="block-not-available">GitHub недоступен</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($constraintAllows === false) : ?>
        <div class="block-warning w-full mt-20">
            <i class="icon-alert-octagon"></i>
            <div class="body-title-2">
                Релиз v<?= Html::encode($latestVersion) ?> не попадает в ограничение
                <code><?= Html::encode($constraint) ?></code>. Composer обновит пакет только в его пределах.
                Для перехода на новую мажорную версию измените ограничение в composer.json.
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="wg-box mb-20">
    <h4 class="mb-16">Проверка окружения</h4>
    <ul class="flex flex-column gap10">
        <?php foreach ($checks as $check) : ?>
            <li class="flex items-center gap10">
                <?php if ($check['ok']) : ?>
                    <span class="block-available" style="min-width:60px;text-align:center;">ok</span>
                <?php else : ?>
                    <span class="block-not-available" style="min-width:60px;text-align:center;">нет</span>
                <?php endif; ?>
                <div>
                    <div class="body-text"><?= Html::encode($check['label']) ?></div>
                    <?php if (!$check['ok']) : ?>
                        <div class="body-text" style="color:#dc2626;font-size:13px;"><?= Html::encode($check['hint']) ?></div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="flex items-center gap10 mt-20 flex-wrap">
        <button type="button" id="btn-update-start" class="tf-button w208"
                <?= ($canRun && !$isRunning) ? '' : 'disabled' ?>>
            <i class="icon-arrow-up-circle"></i>
            <?= $isRunning ? 'Выполняется...' : 'Обновить сейчас' ?>
        </button>
        <a href="<?= Url::to(['update']) ?>" class="tf-button style-2"><i class="icon-refresh-ccw"></i></a>
        <span class="body-text" id="update-status-text" style="color:#64748b;">
            <?= Html::encode($state['message'] ?? '') ?>
        </span>
    </div>

    <?php if (!$canRun) : ?>
        <div class="block-warning type-main w-full mt-20">
            <i class="icon-alert-octagon"></i>
            <div class="body-title-2">
                Запуск из админки недоступен. Выполните обновление вручную на сервере или добавьте задачу в cron (см. ниже).
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="wg-box mb-20" id="update-log-box" <?= ($log === '' && !$isRunning) ? 'style="display:none;"' : '' ?>>
    <div class="flex items-center justify-between">
        <h4>Лог обновления</h4>
        <span class="body-text" style="color:#64748b;" id="update-log-meta">
            <?php if (!empty($state['started_at'])) : ?>
                начато <?= date('d.m.Y H:i:s', (int)$state['started_at']) ?>
            <?php endif; ?>
        </span>
    </div>
    <pre id="update-log" style="margin-top:12px;max-height:480px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:16px;border-radius:12px;font-size:12px;line-height:1.5;white-space:pre-wrap;"><?= Html::encode($log) ?></pre>
</div>

<div class="wg-box mb-20">
    <h4 class="mb-16">Ручной запуск и cron</h4>
    <p class="body-text mb-10">Команда делает то же самое, что кнопка выше: composer update пакета, миграции, очистка кэша, запись версии.</p>
    <pre class="mb-16" style="background:#f1f5f9;padding:12px 16px;border-radius:8px;font-size:13px;overflow:auto;"><?= Html::encode($manualCmd) ?></pre>

    <p class="body-text mb-10">Чтобы обновляться автоматически (например, каждую ночь в 4:00), добавьте в crontab пользователя-владельца файлов проекта:</p>
    <pre class="mb-16" style="background:#f1f5f9;padding:12px 16px;border-radius:8px;font-size:13px;overflow:auto;"><?= Html::encode($cronLine) ?></pre>

    <p class="body-text mb-10">Проверить, что видит команда (composer, PHP, права), без запуска обновления:</p>
    <pre style="background:#f1f5f9;padding:12px 16px;border-radius:8px;font-size:13px;overflow:auto;"><?= Html::encode($phpPath . ' ' . $yiiScript . ' admin/update/check') ?></pre>

    <div class="block-warning type-main w-full mt-20">
        <i class="icon-alert-octagon"></i>
        <div class="body-title-2">
            Для команды в консольном конфиге должны быть подключены модуль <code>admin</code> (в <code>bootstrap</code> и <code>modules</code>)
            и компоненты <code>settings</code>, <code>cache</code>, <code>authManager</code>, <code>db</code>.
        </div>
    </div>
</div>

<?php
$startUrl = Url::to(['update-start']);
$statusUrl = Url::to(['update-status']);
$initialState = Json::encode($state);
$isRunningJs = $isRunning ? 'true' : 'false';
$currentVersionJs = Json::encode($currentVersion);

$this->registerJs(<<<JS
(function () {
    var btn = document.getElementById('btn-update-start');
    var logBox = document.getElementById('update-log-box');
    var logEl = document.getElementById('update-log');
    var statusText = document.getElementById('update-status-text');
    var timer = null;
    var running = {$isRunningJs};
    var startedVersion = {$currentVersionJs};

    function setRunning(flag) {
        running = flag;
        btn.disabled = flag;
        btn.innerHTML = '<i class="icon-arrow-up-circle"></i> ' + (flag ? 'Выполняется...' : 'Обновить сейчас');
    }

    function poll() {
        fetch('{$statusUrl}', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { return; } // во время замены файлов возможны ошибки — просто ждём
                var st = data.state || {};
                logBox.style.display = '';
                var atBottom = logEl.scrollHeight - logEl.scrollTop - logEl.clientHeight < 40;
                logEl.textContent = data.log || '';
                if (atBottom) { logEl.scrollTop = logEl.scrollHeight; }
                statusText.textContent = st.message || (st.status === 'running' ? 'Выполняется...' : '');
                if (st.status === 'finished' || st.status === 'failed') {
                    clearInterval(timer);
                    timer = null;
                    setRunning(false);
                    if (st.status === 'finished') {
                        statusText.textContent = 'Обновление завершено' + (st.version_to ? ': v' + st.version_to : '') + '. Страница перезагрузится...';
                        setTimeout(function () { window.location.reload(); }, 2500);
                    }
                }
            })
            .catch(function () {});
    }

    function startPolling() {
        if (timer) { return; }
        poll();
        timer = setInterval(poll, 2000);
    }

    btn.addEventListener('click', function () {
        if (running) { return; }
        if (!confirm('Запустить обновление админки? Во время обновления админка может быть недоступна несколько секунд.')) { return; }
        setRunning(true);
        var fd = new FormData();
        fd.append(yii.getCsrfParam(), yii.getCsrfToken());
        fetch('{$startUrl}', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    setRunning(false);
                    statusText.textContent = data.message || 'Не удалось запустить обновление';
                    return;
                }
                logBox.style.display = '';
                startPolling();
            })
            .catch(function () {
                setRunning(false);
                statusText.textContent = 'Ошибка запроса';
            });
    });

    if (running) { startPolling(); }
})();
JS
);
