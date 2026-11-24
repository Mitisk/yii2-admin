<?php
/**
 * Раздел «Защита»: настройки WAF, заблокированные адреса, журнал.
 *
 * @var yii\web\View $this
 * @var \Mitisk\Waf\Config $config действующий конфиг (код + настройки админки)
 * @var array<string, mixed> $saved настройки, сохранённые из админки
 * @var bool $running WAF подключён в web/index.php
 * @var string $storageDir
 * @var array<int, array<string, mixed>> $blocks
 * @var int $blocksTotal
 * @var array<int, array<string, mixed>> $events
 * @var array{bans: int, active: int, byDay: array<string, int>} $stats
 * @var string $myIp
 * @var string|null $untrustedProxy адрес прокси, который WAF принимает за посетителя
 * @var string $checkUri
 * @var string|null $checkPattern
 */

use yii\helpers\Html;
use yii\helpers\Url;

$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken());
$hours = static fn (int $seconds): string => rtrim(rtrim(number_format($seconds / 3600, 2, '.', ''), '0'), '.');
$when = static fn (int $time): string => date('d.m.Y H:i', $time);

$actionLabels = [
    'ban' => ['Заблокирован', 'block-not-available'],
    'manual_ban' => ['Заблокирован вручную', 'block-not-available'],
    'observe' => ['Замечен (наблюдение)', 'block-pending'],
    'skip' => ['Пропущен без блокировки', 'block-pending'],
    'captcha_ok' => ['Прошёл каптчу', 'block-available'],
    'captcha_fail' => ['Не прошёл каптчу', 'block-pending'],
    'unban' => ['Разблокирован', 'block-available'],
];
$skipNotes = ['cross-site' => 'запрос со стороннего сайта', 'bot' => 'поисковый робот'];
$disabled = array_map('strtolower', $config->disabledPatterns);

if (!$config->enabled) {
    $state = ['Выключена', 'block-not-available', 'Запросы не проверяются.'];
} elseif ($config->observeOnly) {
    $state = ['Режим наблюдения', 'block-pending', 'Срабатывания пишутся в журнал, никто не блокируется.'];
} else {
    $state = ['Включена', 'block-available', 'Сканеры блокируются.'];
}
$this->params['recordCount'] = $blocksTotal;
?>

<?php if (!$running) : ?>
    <div class="block-warning type-main w-full mb-20">
        <i class="icon-alert-octagon"></i>
        <div class="body-title-2">
            WAF установлен, но не подключён: в этом запросе он не запускался. Добавьте в <code>web/index.php</code>
            сразу после автозагрузчика Composer:
            <code>\Mitisk\Waf\LightweightWaf::run(['runtimePath' => __DIR__ . '/../runtime']);</code>
            Настройки ниже начнут действовать после подключения.
        </div>
    </div>
<?php endif; ?>

<?php if ($untrustedProxy !== null) : ?>
    <div class="block-warning type-main w-full mb-20">
        <i class="icon-alert-octagon"></i>
        <div class="body-title-2">
            Похоже, сайт работает за прокси или CDN, а WAF об этом не знает: запрос пришёл с заголовком прокси,
            но вашим адресом WAF считает <?= Html::encode($untrustedProxy) ?> — это адрес самого прокси.
            Так все посетители для него — один адрес, и первый же сканер закроет сайт для всех.
            Укажите в <code>web/index.php</code> параметры <code>trustedHeaders</code> и <code>trustedProxies</code>
            (все прокси цепочки, включая CDN) или включите режим наблюдения, пока это не сделано.
        </div>
    </div>
<?php endif; ?>

<div class="tf-section-4 mb-20">
    <div class="wg-chart-default">
        <div class="body-text mb-2">Защита</div>
        <div class="<?= $state[1] ?>" style="display:inline-block;"><?= $state[0] ?></div>
        <div class="body-text mt-2"><?= $state[2] ?></div>
    </div>
    <div class="wg-chart-default">
        <div class="body-text mb-2">Заблокировано за 7 дней</div>
        <h4><?= number_format($stats['bans'], 0, '.', ' ') ?></h4>
        <div class="body-text">сейчас под блокировкой: <?= number_format($stats['active'], 0, '.', ' ') ?></div>
    </div>
    <div class="wg-chart-default">
        <div class="body-text mb-2">Ваш адрес</div>
        <h4 style="font-size:18px;"><?= Html::encode($myIp) ?></h4>
        <div class="body-text">
            <?= \Mitisk\Waf\IpTools::inList($myIp, $config->allowIps) ? 'в белом списке' : 'не в белом списке' ?>
        </div>
    </div>
    <div class="wg-chart-default">
        <div class="body-text mb-2">Версия WAF</div>
        <h4 style="font-size:18px;"><?= Html::encode(\Mitisk\Waf\LightweightWaf::VERSION) ?></h4>
        <div class="body-text"><?= $saved === [] ? 'настройки из web/index.php' : 'настройки из админки' ?></div>
    </div>
</div>

<ul class="nav comp-nav-tabs" id="wafTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab">
            <i class="icon-settings me-1"></i> Настройки
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-blocks" type="button" role="tab">
            <i class="icon-slash me-1"></i> Заблокированные (<?= $blocksTotal ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-events" type="button" role="tab">
            <i class="icon-list me-1"></i> Журнал
        </button>
    </li>
</ul>

<div class="tab-content comp-tab-content">

<?php /* ─── Настройки ─────────────────────────────────────── */ ?>
<div class="tab-pane fade show active" id="tab-settings" role="tabpanel">
    <form method="post" action="<?= Url::to(['save']) ?>">
        <?= $csrf ?>

        <div class="wg-box waf-box mb-20">
            <div class="left">
                <h5 class="mb-4">Режим</h5>
                <div class="body-text">Что делать с адресом, который запросил путь из списка шаблонов.</div>
            </div>
            <div class="right flex-grow">
                <?php foreach ([
                    'enabled' => ['Защита включена', 'Выключенная защита не проверяет запросы вообще.'],
                    'observeOnly' => ['Режим наблюдения', 'Только записывать срабатывания в журнал, никого не блокировать. Удобно при первом включении: посмотрите журнал пару дней и убедитесь, что под шаблоны не попадают обычные посетители.'],
                    'permanentBlock' => ['Блокировать бессрочно', 'Иначе блокировка снимается сама через срок ниже.'],
                ] as $key => [$label, $hint]) : ?>
                    <fieldset class="mb-10">
                        <input type="hidden" name="Waf[<?= $key ?>]" value="0">
                        <div class="flex gap10 items-center">
                            <input class="total-checkbox" type="checkbox" id="waf-<?= $key ?>" name="Waf[<?= $key ?>]" value="1" <?= $config->{$key} ? 'checked' : '' ?>>
                            <label for="waf-<?= $key ?>" class="body-title"><?= $label ?></label>
                        </div>
                        <div class="body-text"><?= $hint ?></div>
                    </fieldset>
                <?php endforeach; ?>
                <fieldset class="mb-10">
                    <div class="body-title mb-10">Срок блокировки, часов</div>
                    <input type="number" class="form-control" name="Waf[blockHours]" min="0.02" step="any" value="<?= $hours($config->blockDuration) ?>" style="max-width:200px;">
                </fieldset>
            </div>
        </div>

        <div class="wg-box waf-box mb-20">
            <div class="left">
                <h5 class="mb-4">Шаблоны</h5>
                <div class="body-text">
                    Часть пути запроса, без учёта регистра. Параметры после «?» не учитываются.
                    Если на сайте есть настоящий адрес из списка — снимите с него отметку.
                </div>
            </div>
            <div class="right flex-grow">
                <fieldset class="mb-10">
                    <div class="body-title mb-10">Встроенные</div>
                    <div class="waf-patterns">
                        <?php foreach ($config->basePatterns as $i => $pattern) : $pattern = strtolower($pattern); ?>
                            <label class="waf-pattern">
                                <input type="hidden" name="Waf[basePatterns][]" value="<?= Html::encode($pattern) ?>">
                                <input type="checkbox" name="Waf[enabledPatterns][]" value="<?= Html::encode($pattern) ?>" <?= in_array($pattern, $disabled, true) ? '' : 'checked' ?>>
                                <code><?= Html::encode($pattern) ?></code>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="mb-10">
                    <div class="body-title mb-10">Свои шаблоны</div>
                    <textarea name="Waf[extraPatterns]" rows="4" placeholder="/old-cms/&#10;/backup.sql"><?= Html::encode(implode("\n", $config->extraPatterns)) ?></textarea>
                    <div class="body-text">По одному в строке, не короче 3 символов. Шаблон, под который попадает главная или сама админка, не сохранится.</div>
                </fieldset>
            </div>
        </div>

        <div class="wg-box waf-box mb-20">
            <div class="left">
                <h5 class="mb-4">Кого не блокировать</h5>
                <div class="body-text">Адреса из белого списка не проверяются вообще: офис, мониторинг, ваш собственный адрес.</div>
            </div>
            <div class="right flex-grow">
                <fieldset class="mb-10">
                    <div class="body-title mb-10">Белый список</div>
                    <textarea name="Waf[allowIps]" id="waf-allow-ips" rows="4" placeholder="203.0.113.5&#10;10.0.0.0/8"><?= Html::encode(implode("\n", $config->allowIps)) ?></textarea>
                    <div class="body-text">
                        По одному адресу или сети (CIDR) в строке.
                        <a href="javascript:void(0);" id="waf-add-my-ip" data-ip="<?= Html::encode($myIp) ?>">Добавить мой адрес (<?= Html::encode($myIp) ?>)</a>
                    </div>
                </fieldset>
                <?php foreach ([
                    'skipCrossSite' => ['Не блокировать за запросы со сторонних сайтов', 'Чужая страница может незаметно заставить браузер посетителя запросить «плохой» адрес вашего сайта. Такой запрос получит 404, но посетитель не будет заблокирован.'],
                    'verifyBots' => ['Не блокировать поисковых роботов', 'Роботы Google, Яндекса и Bing, подтверждённые по DNS, получают 404 без блокировки. Тот, кто только называет себя роботом, блокируется.'],
                ] as $key => [$label, $hint]) : ?>
                    <fieldset class="mb-10">
                        <input type="hidden" name="Waf[<?= $key ?>]" value="0">
                        <div class="flex gap10 items-center">
                            <input class="total-checkbox" type="checkbox" id="waf-<?= $key ?>" name="Waf[<?= $key ?>]" value="1" <?= $config->{$key} ? 'checked' : '' ?>>
                            <label for="waf-<?= $key ?>" class="body-title"><?= $label ?></label>
                        </div>
                        <div class="body-text"><?= $hint ?></div>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="wg-box waf-box mb-20">
            <div class="left">
                <h5 class="mb-4">Каптча</h5>
                <div class="body-text">С ключами Google reCAPTCHA v2 заблокированный человек снимает блокировку сам. Без ключей он видит страницу 403 до конца срока.</div>
            </div>
            <div class="right flex-grow">
                <fieldset class="mb-10">
                    <div class="body-title mb-10">Ключ сайта</div>
                    <input type="text" class="form-control" name="Waf[recaptchaSiteKey]" value="<?= Html::encode((string)$config->recaptchaSiteKey) ?>" autocomplete="off">
                </fieldset>
                <fieldset class="mb-10">
                    <div class="body-title mb-10">Секретный ключ</div>
                    <input type="text" class="form-control" name="Waf[recaptchaSecret]" value="<?= Html::encode((string)$config->recaptchaSecret) ?>" autocomplete="off">
                </fieldset>
                <div class="flex gap20">
                    <fieldset class="mb-10">
                        <div class="body-title mb-10">Попыток каптчи</div>
                        <input type="number" class="form-control" name="Waf[captchaMaxAttempts]" min="1" max="100" value="<?= (int)$config->captchaMaxAttempts ?>">
                    </fieldset>
                    <fieldset class="mb-10">
                        <div class="body-title mb-10">за сколько минут</div>
                        <input type="number" class="form-control" name="Waf[captchaWindowMinutes]" min="1" max="1440" value="<?= (int)round($config->captchaWindow / 60) ?>">
                    </fieldset>
                </div>
            </div>
        </div>

        <div class="wg-box waf-box mb-20">
            <div class="left">
                <h5 class="mb-4">Задаётся в коде</h5>
                <div class="body-text">Эти параметры меняются только в <code>web/index.php</code>: ошибка в них превращает всех посетителей в один адрес.</div>
            </div>
            <div class="right flex-grow">
                <div class="body-text mb-10">Доверенные прокси:
                    <b><?= $config->trustedProxies ? Html::encode(implode(', ', $config->trustedProxies)) : 'нет — адрес посетителя берётся из соединения' ?></b></div>
                <div class="body-text mb-10">Заголовки с адресом посетителя:
                    <b><?= $config->trustedHeaders ? Html::encode(implode(', ', $config->trustedHeaders)) : 'не читаются' ?></b></div>
                <div class="body-text mb-10">Искать шаблоны в параметрах запроса: <b><?= $config->matchQuery ? 'да' : 'нет' ?></b></div>
                <div class="body-text">Хранилище: <code><?= Html::encode($storageDir) ?></code></div>
            </div>
        </div>

        <div class="flex gap10 mb-20">
            <button type="submit" class="tf-button w208">Сохранить</button>
            <?php if ($saved !== []) : ?>
                <button type="submit" class="tf-button style-2" formaction="<?= Url::to(['reset']) ?>"
                        onclick="return confirm('Убрать настройки из админки и вернуться к конфигу из web/index.php?');">
                    Вернуть настройки из кода
                </button>
            <?php endif; ?>
        </div>
    </form>

    <div class="wg-box waf-box">
        <div class="left">
            <h5 class="mb-4">Проверить адрес</h5>
            <div class="body-text">Сработает ли защита на этом адресе при сохранённых настройках.</div>
        </div>
        <div class="right flex-grow">
            <form method="get" action="<?= Url::to(['index']) ?>" class="flex gap10">
                <input type="text" class="form-control" name="check" value="<?= Html::encode($checkUri) ?>" placeholder="/catalog/item-15">
                <button type="submit" class="tf-button style-2">Проверить</button>
            </form>
            <?php if ($checkUri !== '') : ?>
                <div class="mt-3 <?= $checkPattern === null ? 'block-available' : 'block-not-available' ?>" style="display:inline-block;">
                    <?= $checkPattern === null
                        ? 'Адрес безопасен: ни один шаблон не подходит.'
                        : 'Запросивший этот адрес будет заблокирован: шаблон «' . Html::encode($checkPattern) . '».' ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php /* ─── Заблокированные ───────────────────────────────── */ ?>
<div class="tab-pane fade" id="tab-blocks" role="tabpanel">
    <form method="post" action="<?= Url::to(['block']) ?>" class="toolbar-card toolbar-compact">
        <?= $csrf ?>
        <input type="text" class="form-control" name="ip" placeholder="IP-адрес" required>
        <input type="number" class="form-control" name="hours" value="24" min="0" step="any" title="Срок в часах; 0 — бессрочно" style="max-width:110px;">
        <input type="text" class="form-control" name="note" placeholder="Причина (для себя)">
        <button type="submit" class="tf-button">Заблокировать</button>
    </form>

    <div class="table-card">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                <tr>
                    <th style="width:190px;">Адрес</th>
                    <th style="width:140px;">Когда</th>
                    <th style="width:140px;">До</th>
                    <th>За что</th>
                    <th style="width:230px;"></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($blocks === []) : ?>
                    <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--Note);">Сейчас никто не заблокирован</td></tr>
                <?php endif; ?>
                <?php foreach ($blocks as $block) :
                    $key = (string)($block['key'] ?? '');
                    $ip = (string)($block['ip'] ?? $key);
                    ?>
                    <tr>
                        <td>
                            <b><?= Html::encode($ip) ?></b>
                            <?php if ($key !== $ip) : ?><br><span style="font-size:11px;">сеть <?= Html::encode($key) ?></span><?php endif; ?>
                        </td>
                        <td><?= $when((int)($block['at'] ?? 0)) ?></td>
                        <td><?= (int)($block['until'] ?? 0) === 0 ? 'бессрочно' : $when((int)$block['until']) ?></td>
                        <td>
                            <?php if (($block['reason'] ?? '') === 'manual') : ?>
                                вручную<?= !empty($block['note']) ? ': ' . Html::encode((string)$block['note']) : '' ?>
                            <?php elseif (($block['reason'] ?? '') === 'legacy') : ?>
                                перенесён из прежнего списка
                            <?php else : ?>
                                <code><?= Html::encode((string)($block['uri'] ?? '')) ?></code>
                                <br><span style="font-size:11px;"><?= Html::encode((string)($block['ua'] ?? '')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex gap10">
                                <form method="post" action="<?= Url::to(['unblock']) ?>">
                                    <?= $csrf ?><?= Html::hiddenInput('key', $key) ?>
                                    <button type="submit" class="tf-button style-2" style="height:34px;">Разблокировать</button>
                                </form>
                                <form method="post" action="<?= Url::to(['allow-ip']) ?>">
                                    <?= $csrf ?><?= Html::hiddenInput('ip', $ip) ?>
                                    <button type="submit" class="tf-button style-2" style="height:34px;" title="Разблокировать и больше не проверять этот адрес">В белый список</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($blocksTotal > count($blocks)) : ?>
        <div class="body-text mt-3">Показаны последние <?= count($blocks) ?> из <?= $blocksTotal ?>.</div>
    <?php endif; ?>
</div>

<?php /* ─── Журнал ────────────────────────────────────────── */ ?>
<div class="tab-pane fade" id="tab-events" role="tabpanel">
    <div class="table-card">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                <tr>
                    <th style="width:140px;">Когда</th>
                    <th style="width:210px;">Что</th>
                    <th style="width:190px;">Адрес</th>
                    <th>Запрос</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($events === []) : ?>
                    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--Note);">Срабатываний пока не было</td></tr>
                <?php endif; ?>
                <?php foreach ($events as $event) :
                    [$label, $class] = $actionLabels[$event['action'] ?? ''] ?? [(string)($event['action'] ?? ''), 'block-pending'];
                    $note = (string)($event['note'] ?? '');
                    ?>
                    <tr>
                        <td><?= $when((int)($event['time'] ?? 0)) ?></td>
                        <td>
                            <div class="<?= $class ?>" style="display:inline-block;"><?= $label ?></div>
                            <?php if ($note !== '') : ?><br><span style="font-size:11px;"><?= Html::encode($skipNotes[$note] ?? $note) ?></span><?php endif; ?>
                        </td>
                        <td><?= Html::encode((string)($event['ip'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($event['uri'])) : ?>
                                <code><?= Html::encode((string)$event['uri']) ?></code>
                                <?php if (!empty($event['pattern'])) : ?> <span style="font-size:11px;">шаблон «<?= Html::encode((string)$event['pattern']) ?>»</span><?php endif; ?>
                                <br><span style="font-size:11px;"><?= Html::encode((string)($event['ua'] ?? '')) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="body-text mt-3">Последние <?= count($events) ?> событий. Журнал хранит около 2 МБ записей, старые вытесняются.</div>
</div>

</div>

<?php
$this->registerCss(<<<'CSS'
/* Две колонки, как на странице «Настройки»: слева заголовок и пояснение, справа поля */
.waf-box { display: flex; flex-direction: row; gap: 30px; }
.waf-box > .left { width: 100%; max-width: 320px; flex: none; }
.waf-box > .right { min-width: 0; }
.waf-box fieldset { border: 0; padding: 0; }
.waf-box textarea { width: 100%; min-height: 96px; }
@media (max-width: 992px) { .waf-box { flex-wrap: wrap; } .waf-box > .left { max-width: unset; } }
.waf-patterns { display: flex; flex-wrap: wrap; gap: 8px 18px; }
.waf-pattern { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; min-width: 170px; }
.waf-pattern code { font-size: 13px; }
CSS);
$this->registerJs(<<<'JS'
(function () {
    var link = document.getElementById('waf-add-my-ip');
    if (link) {
        link.addEventListener('click', function () {
            var area = document.getElementById('waf-allow-ips');
            var lines = area.value.split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
            if (lines.indexOf(link.dataset.ip) === -1) { lines.push(link.dataset.ip); }
            area.value = lines.join('\n');
            area.focus();
        });
    }
    // После действия со вкладки «Заблокированные» возвращаемся на неё (#blocks, #events).
    // По событию load: bootstrap подключается позже этого скрипта.
    window.addEventListener('load', function () {
        var target = {'#blocks': '#tab-blocks', '#events': '#tab-events'}[window.location.hash];
        var btn = target && document.querySelector('#wafTabs [data-bs-target="' + target + '"]');
        if (btn && window.bootstrap) { new bootstrap.Tab(btn).show(); }
    });
})();
JS);
