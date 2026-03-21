<?php
/**
 * Страница логов: аудит действий + системный лог.
 *
 * @var \yii\web\View                $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 * @var array                        $usersList
 * @var string|null                  $filterUser
 * @var string|null                  $filterAction
 * @var string                       $filterSearch
 * @var string|null                  $filterDateFrom
 * @var string|null                  $filterDateTo
 */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

$this->title = 'Журнал действий';
$this->params['breadcrumbs'][] = $this->title;
$this->params['recordCount'] = $dataProvider->getTotalCount();
?>

<?php /* ── Вкладки ────────────────────────────────── */ ?>
<ul class="nav comp-nav-tabs" id="logTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-audit-btn"
                data-bs-toggle="tab" data-bs-target="#tab-audit"
                type="button" role="tab">
            <i class="icon-shield me-1"></i> Аудит действий
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-syslog-btn"
                data-bs-toggle="tab" data-bs-target="#tab-syslog"
                type="button" role="tab">
            <i class="icon-monitor me-1"></i> Системный лог
        </button>
    </li>
</ul>

<div class="tab-content comp-tab-content" id="logTabContent">

<?php /* ─── TAB 1: Аудит действий ─────────────────── */ ?>
<div class="tab-pane fade show active" id="tab-audit" role="tabpanel">

    <?php Pjax::begin(['id' => 'audit-pjax']); ?>

    <form method="get" action="<?= Url::to(['log/index']) ?>"
          data-pjax="1" class="toolbar-card toolbar-compact">
        <?= Html::dropDownList('user_id', $filterUser,
            ['' => 'Пользователь'] + $usersList,
            ['class' => 'form-control']) ?>
        <?= Html::dropDownList('action', $filterAction, [
            '' => 'Действие',
            'create' => 'Создание',
            'update' => 'Изменение',
            'delete' => 'Удаление',
        ], ['class' => 'form-control']) ?>
        <?= Html::textInput('search', $filterSearch, [
            'class' => 'form-control',
            'placeholder' => 'Поиск по записи...',
        ]) ?>
        <?= Html::input('date', 'date_from', $filterDateFrom, [
            'class' => 'form-control',
            'title' => 'Дата с',
        ]) ?>
        <?= Html::input('date', 'date_to', $filterDateTo, [
            'class' => 'form-control',
            'title' => 'Дата по',
        ]) ?>
        <button type="submit" class="tf-button"><i class="icon-search"></i></button>
        <a href="<?= Url::to(['log/index']) ?>" class="tf-button style-2"><i class="icon-x"></i></a>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                <tr>
                    <th style="width:130px;">Дата</th>
                    <th style="width:170px;">Пользователь</th>
                    <th style="width:100px;">Действие</th>
                    <th>Запись</th>
                    <th style="width:90px;">Изменения</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($dataProvider->getCount() === 0) : ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:40px;color:var(--Note);">
                            Записей не найдено
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($dataProvider->getModels() as $log) :
                        /** @var \Mitisk\Yii2Admin\models\AuditLog $log */
                        $user = $log->user;
                        $diffArr = $log->getDiffArray();
                        $rowId = 'diff-' . $log->id;
                    ?>
                    <tr>
                        <td>
                            <span class="date-cell">
                                <i class="icon-calendar"></i>
                                <?= date('d.m.Y', $log->created_at) ?>
                                <br><span style="font-size:11px;"><?= date('H:i:s', $log->created_at) ?></span>
                            </span>
                        </td>
                        <td>
                            <?php if ($user) : ?>
                            <div class="cell-user">
                                <?= Html::img($user->getAvatar(), [
                                    'width' => 28, 'height' => 28,
                                ]) ?>
                                <div class="cell-user-name">
                                    <?= Html::encode($user->name ?: $user->username) ?>
                                </div>
                            </div>
                            <?php else : ?>
                                <span style="color:var(--Note);">Система</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="<?= $log->getActionBadgeStyle() ?>padding:2px 10px;border-radius:4px;font-size:11px;font-weight:600;">
                                <?= Html::encode($log->getActionLabel()) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $url = $log->getModelUrl();
                            $label = Html::encode($log->model_label ?: ('ID: ' . $log->model_id));
                            if ($url) {
                                echo Html::a($label, $url, ['class' => 'cell-user-link', 'style' => 'font-weight:600;']);
                            } else {
                                echo '<strong>' . $label . '</strong>';
                            }
                            $short = \yii\helpers\StringHelper::basename($log->model_class);
                            echo '<div style="font-size:11px;color:var(--Note);">' . Html::encode($short) . ' #' . Html::encode($log->model_id) . '</div>';
                            ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if (!empty($diffArr)) : ?>
                                <button type="button" class="btn-action view"
                                    title="Показать diff"
                                    onclick="var e=document.getElementById('<?= $rowId ?>');e.style.display=e.style.display==='none'?'table-row':'none';">
                                    <i class="icon-git-pull-request"></i>
                                </button>
                            <?php elseif ($log->action === 'create') : ?>
                                <span style="font-size:11px;color:#16a34a;">новая</span>
                            <?php elseif ($log->action === 'delete') : ?>
                                <span style="font-size:11px;color:#dc2626;">удалена</span>
                            <?php else : ?>
                                <span style="color:var(--Icon);">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($diffArr)) : ?>
                    <tr id="<?= $rowId ?>" style="display:none;">
                        <td colspan="5" style="padding:0 20px 16px;">
                            <table class="diff-table">
                                <thead>
                                <tr>
                                    <th style="width:30%;">Поле</th>
                                    <th style="width:35%;">Было</th>
                                    <th style="width:35%;">Стало</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($diffArr as $fld => $vals) :
                                    $old = $vals[0] ?? '';
                                    $new = $vals[1] ?? '';
                                ?>
                                <tr>
                                    <td class="diff-field"><?= Html::encode($fld) ?></td>
                                    <td class="diff-old">
                                        <?= ($old !== '' && $old !== null)
                                            ? Html::encode(mb_strimwidth((string)$old, 0, 200, '...'))
                                            : '<span style="color:var(--Icon);">&mdash;</span>' ?>
                                    </td>
                                    <td class="diff-new">
                                        <?= ($new !== '' && $new !== null)
                                            ? Html::encode(mb_strimwidth((string)$new, 0, 200, '...'))
                                            : '<span style="color:var(--Icon);">&mdash;</span>' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-wrapper">
            <div class="text-muted" style="font-size:13px;">
                Всего: <?= $dataProvider->getTotalCount() ?>
            </div>
            <?= \Mitisk\Yii2Admin\widgets\LinkPager::widget([
                'pagination' => $dataProvider->getPagination(),
            ]) ?>
        </div>
    </div>

    <?php Pjax::end(); ?>

    <?php if (Yii::$app->user->can('superAdminRole')) : ?>
    <div style="margin-top:16px;">
        <?= Html::a(
            '<i class="icon-trash-2 me-1"></i> Очистить журнал',
            ['log/clear-audit'],
            [
                'class' => 'tf-button tf-button-danger',
                'data-confirm' => 'Вы уверены? Все записи аудита будут удалены.',
                'data-method' => 'post',
            ]
        ) ?>
    </div>
    <?php endif; ?>

</div>

<?php /* ─── TAB 2: Системный лог ──────────────────── */ ?>
<div class="tab-pane fade" id="tab-syslog" role="tabpanel">

    <div class="toolbar-card toolbar-compact">
        <select id="syslog-level" class="form-control">
            <option value="">Уровень</option>
            <option value="error">Error</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
        </select>
        <input type="text" id="syslog-search" class="form-control" placeholder="Поиск по сообщению...">
        <button type="button" class="tf-button" onclick="syslogLoad(true)"><i class="icon-search"></i></button>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="modern-table" id="syslog-table">
                <thead>
                <tr>
                    <th style="width:130px;">Дата</th>
                    <th style="width:100px;">IP</th>
                    <th style="width:90px;">Уровень</th>
                    <th>Сообщение</th>
                </tr>
                </thead>
                <tbody id="syslog-body">
                <tr id="syslog-empty">
                    <td colspan="4" style="text-align:center;padding:40px;color:var(--Note);">
                        <i class="icon-loader" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                        Загрузка...
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-wrapper" id="syslog-footer" style="display:none;">
            <div id="syslog-total" class="text-muted" style="font-size:13px;"></div>
            <button type="button" id="syslog-more" class="tf-button style-2" style="display:none;" onclick="syslogLoad(false)">
                <i class="icon-chevron-down me-1"></i> Загрузить ещё
            </button>
        </div>
    </div>

</div>

</div>

<?php
$syslogUrl = Url::to(['log/system-log']);
$this->registerJs(<<<JS
window.syslogOffset = 0;
var syslogLimit = 100;

var levelBadges = {
    error:   'background:#fee2e2;color:#991b1b;',
    warning: 'background:#fef3c7;color:#92400e;',
    info:    'background:#d1fae5;color:#065f46;'
};

function esc(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

window.syslogLoad = function(reset) {
    if (reset) window.syslogOffset = 0;
    var level  = document.getElementById('syslog-level').value;
    var search = document.getElementById('syslog-search').value;
    var url = '{$syslogUrl}?offset=' + window.syslogOffset
        + '&limit=' + syslogLimit
        + '&level=' + encodeURIComponent(level)
        + '&search=' + encodeURIComponent(search);

    fetch(url).then(function(r){return r.json();}).then(function(data){
        var tbody = document.getElementById('syslog-body');
        if (reset) tbody.innerHTML = '';
        var empty = document.getElementById('syslog-empty');
        if (empty) empty.remove();
        document.getElementById('syslog-footer').style.display = 'flex';

        if (!data.items || data.items.length === 0) {
            if (reset) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--Note);">Записей не найдено</td></tr>';
            }
            document.getElementById('syslog-more').style.display = 'none';
            return;
        }

        var uid = 0;
        data.items.forEach(function(row) {
            uid++;
            var rid = 'slog-' + window.syslogOffset + '-' + uid;
            var b = levelBadges[row.level] || 'background:var(--bg-table-1);color:var(--Heading);';
            var p = row.datetime.split(' ');
            var d = p[0] ? p[0].split('-').reverse().join('.') : '';
            var t = p[1] || '';
            var msg = esc(row.message);
            var full = esc(row.full || '');
            var hasFull = full && full !== msg && full.length > msg.length;

            var tr = document.createElement('tr');
            var cursor = hasFull ? 'cursor:pointer;' : '';
            var click = hasFull
                ? ' onclick="var e=document.getElementById(\'' + rid + '\');e.style.display=e.style.display===\'none\'?\'table-row\':\'none\';"'
                : '';
            var icon = hasFull ? '<i class="icon-chevron-right" style="font-size:10px;color:var(--Note);margin-right:6px;"></i>' : '';

            tr.innerHTML =
                '<td><span class="date-cell"><i class="icon-calendar"></i> ' + d + '<br><span style="font-size:11px;">' + t + '</span></span></td>'
                + '<td style="font-family:monospace;font-size:12px;">' + esc(row.ip || '-') + '</td>'
                + '<td><span style="' + b + 'padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">' + esc(row.level) + '</span></td>'
                + '<td style="word-break:break-all;' + cursor + '"' + click + '>' + icon + msg + '</td>';
            tbody.appendChild(tr);

            if (hasFull) {
                var tr2 = document.createElement('tr');
                tr2.id = rid;
                tr2.style.display = 'none';
                tr2.innerHTML = '<td colspan="4" style="padding:0 20px 16px;"><pre class="syslog-pre">' + full + '</pre></td>';
                tbody.appendChild(tr2);
            }
        });

        window.syslogOffset += data.items.length;
        document.getElementById('syslog-total').textContent = 'Показано: ' + window.syslogOffset + ' из ' + data.total;
        document.getElementById('syslog-more').style.display = data.hasMore ? 'inline-block' : 'none';
    });
};

document.getElementById('tab-syslog-btn').addEventListener('shown.bs.tab', function() {
    if (window.syslogOffset === 0) window.syslogLoad(true);
});
JS
);
?>
