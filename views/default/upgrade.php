<?php
/**
 * @var yii\web\View $this
 * @var string       $currentVersion
 * @var string       $savedVersion
 * @var int          $pendingCount
 * @var string[]     $pendingList
 */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Обновление системы';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::encode($this->title) ?></title>
    <?= Html::csrfMetaTags() ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f1f5f9;
            --surface: #ffffff;
            --primary: #2275fc;
            --primary-hover: #1a5fd4;
            --success: #16a34a;
            --danger: #dc2626;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .upgrade-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
        }

        .upgrade-header {
            background: linear-gradient(135deg, #1e3a8a, var(--primary));
            color: #fff;
            padding: 32px;
            text-align: center;
        }

        .upgrade-header .icon {
            width: 56px;
            height: 56px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .upgrade-header h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .upgrade-header .versions {
            font-size: 14px;
            opacity: 0.8;
        }

        .upgrade-header .versions b {
            opacity: 1;
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .upgrade-body {
            padding: 32px;
        }

        .migration-info {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .migration-info .count {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .migration-info .count span {
            background: var(--primary);
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 6px;
        }

        .migration-list {
            max-height: 160px;
            overflow-y: auto;
            font-size: 12px;
            font-family: 'Courier New', monospace;
            color: var(--muted);
            line-height: 1.8;
        }

        .migration-list::-webkit-scrollbar {
            width: 4px;
        }

        .migration-list::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }

        .btn-upgrade {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-upgrade:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34,117,252,0.3);
        }

        .btn-upgrade:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-enter {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 10px;
            background: var(--success);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-enter:hover {
            background: #15803d;
            transform: translateY(-1px);
            color: #fff;
        }

        /* Progress */
        .progress-wrap {
            display: none;
            margin-bottom: 20px;
        }

        .progress-bar {
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            width: 0;
            transition: width 0.4s ease;
        }

        .progress-text {
            font-size: 13px;
            color: var(--muted);
            text-align: center;
        }

        /* Log */
        .log-wrap {
            display: none;
            background: #1e293b;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
            max-height: 200px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.6;
            color: #94a3b8;
        }

        .log-wrap .log-ok {
            color: #4ade80;
        }

        .log-wrap .log-err {
            color: #f87171;
        }

        /* Result */
        .result-wrap {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }

        .result-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .result-icon.ok {
            background: #dcfce7;
            color: var(--success);
        }

        .result-icon.fail {
            background: #fee2e2;
            color: var(--danger);
        }

        .result-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .result-sub {
            font-size: 13px;
            color: var(--muted);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
    </style>
</head>
<body>
    <div class="upgrade-card">
        <div class="upgrade-header">
            <div class="icon">&#x2B06;</div>
            <h1>Обновление Admin Panel</h1>
            <div class="versions">
                <?= Html::encode($savedVersion) ?> &rarr;
                <b><?= Html::encode($currentVersion) ?></b>
            </div>
        </div>

        <div class="upgrade-body">
            <?php if ($pendingCount > 0) : ?>
                <div class="migration-info">
                    <div class="count">
                        Миграции базы данных
                        <span><?= $pendingCount ?></span>
                    </div>
                    <div class="migration-list">
                        <?php foreach ($pendingList as $m) : ?>
                            <div><?= Html::encode($m) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="migration-info">
                    <div class="count">
                        Миграций нет — только обновление версии
                    </div>
                </div>
            <?php endif; ?>

            <div class="progress-wrap" id="progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text" id="progressText">
                    Подготовка...
                </div>
            </div>

            <div class="log-wrap" id="log"></div>

            <div class="result-wrap" id="result">
                <div class="result-icon" id="resultIcon"></div>
                <div class="result-title" id="resultTitle"></div>
                <div class="result-sub" id="resultSub"></div>
            </div>

            <button type="button" class="btn-upgrade"
                    id="btnUpgrade"
                    onclick="runUpgrade()">
                &#x26A1; Применить обновление
            </button>

            <a href="<?= Url::to(['/admin/default/index']) ?>"
               class="btn-enter"
               id="btnEnter"
               style="display:none;">
                &#x2714; Войти в панель управления
            </a>
        </div>
    </div>

<script>
function runUpgrade() {
    var btn = document.getElementById('btnUpgrade');
    var progress = document.getElementById('progress');
    var log = document.getElementById('log');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Обновление...';
    progress.style.display = 'block';
    log.style.display = 'block';

    var fill = document.getElementById('progressFill');
    var pText = document.getElementById('progressText');
    fill.style.width = '30%';
    pText.textContent = 'Выполняю миграции...';
    addLog('Запуск обновления...');

    var csrfParam = document.querySelector('meta[name="csrf-param"]');
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var body = {};
    if (csrfParam && csrfToken) {
        body[csrfParam.content] = csrfToken.content;
    }

    fetch('<?= Url::to(["/admin/default/run-migrations"]) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrfToken ? csrfToken.content : ''
        },
        body: JSON.stringify(body)
    })
    .then(function(r) {
        if (!r.ok) {
            return r.text().then(function(t) {
                throw new Error('HTTP ' + r.status + ': '
                    + t.substring(0, 200));
            });
        }
        var ct = r.headers.get('content-type') || '';
        if (ct.indexOf('json') === -1) {
            return r.text().then(function(t) {
                throw new Error('Not JSON: ' + t.substring(0, 200));
            });
        }
        return r.json();
    })
    .then(function(data) {
        fill.style.width = '100%';

        if (data.applied && data.applied.length) {
            data.applied.forEach(function(m) {
                addLog('&#x2714; ' + esc(m), 'log-ok');
            });
        }

        if (data.errors && data.errors.length) {
            data.errors.forEach(function(e) {
                addLog('&#x2716; ' + esc(e), 'log-err');
            });
        }

        if (data.success) {
            pText.textContent = 'Готово!';
            showResult(true, 'Обновление завершено',
                'Версия <?= Html::encode($currentVersion) ?> установлена');
        } else {
            pText.textContent = 'Ошибка';
            showResult(false, 'Ошибка обновления',
                'Проверьте лог выше');
            btn.innerHTML = '&#x26A1; Повторить';
            btn.disabled = false;
        }
    })
    .catch(function(err) {
        fill.style.width = '100%';
        addLog('&#x2716; ' + esc(err.message), 'log-err');
        pText.textContent = 'Ошибка соединения';
        showResult(false, 'Ошибка соединения', err.message);
        btn.innerHTML = '&#x26A1; Повторить';
        btn.disabled = false;
    });
}

function addLog(html, cls) {
    var log = document.getElementById('log');
    var div = document.createElement('div');
    if (cls) div.className = cls;
    div.innerHTML = html;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
}

function showResult(ok, title, sub) {
    var wrap = document.getElementById('result');
    wrap.style.display = 'block';
    document.getElementById('resultIcon').className =
        'result-icon ' + (ok ? 'ok' : 'fail');
    document.getElementById('resultIcon').innerHTML =
        ok ? '&#x2714;' : '&#x2716;';
    document.getElementById('resultTitle').textContent = title;
    document.getElementById('resultSub').textContent = sub;

    if (ok) {
        document.getElementById('btnUpgrade').style.display = 'none';
        document.getElementById('btnEnter').style.display = 'flex';
    }
}

function esc(s) {
    return (s || '').replace(/&/g,'&amp;')
        .replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
