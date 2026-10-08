<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\Module;
use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;

/**
 * Сервис самообновления модуля через composer.
 *
 * Состояние обновления хранится в настройках (`HIDDEN.admin_update_state`),
 * поэтому оно одинаково видно из web- и console-приложения, даже если у них
 * разные `@runtime`. Лог пишется в файл, путь к которому лежит в состоянии.
 *
 * Сценарии запуска:
 *  - из админки: {@see startBackground()} запускает `php yii admin/update`
 *    отсоединённым процессом;
 *  - вручную: `php yii admin/update` в терминале или по cron.
 *
 * @category Component
 * @package  Mitisk\Yii2Admin\components
 * @author   Mitisk <akimkinpit@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/mitisk/yii2-admin
 */
class SelfUpdateService extends Component
{
    public const PACKAGE = 'mitisk/yii2-admin';

    public const STATUS_IDLE = 'idle';
    public const STATUS_STARTING = 'starting';
    public const STATUS_RUNNING = 'running';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_FAILED = 'failed';

    /** Через сколько секунд незавершённое обновление считается зависшим. */
    public const STALE_AFTER = 1800;

    private const STATE_SECTION = 'HIDDEN';
    private const STATE_KEY = 'admin_update_state';

    // ------------------------------------------------------------------
    // Пути и бинарники
    // ------------------------------------------------------------------

    /**
     * Корень composer-проекта (каталог, где лежат composer.json и vendor/).
     */
    public function getProjectRoot(): string
    {
        return dirname(Yii::getAlias('@vendor'));
    }

    /**
     * Путь к консольному скрипту `yii`.
     */
    public function getYiiScript(): ?string
    {
        $candidates = [
            Yii::getAlias('@app') . '/yii',
            $this->getProjectRoot() . '/yii',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Путь к PHP CLI. Под PHP-FPM константа PHP_BINARY указывает на php-fpm,
     * поэтому ищем отдельно.
     */
    public function findPhp(): ?string
    {
        $fromSettings = (string)Yii::$app->settings->get('GENERAL', 'php_path', '');
        if ($fromSettings !== '' && is_executable($fromSettings)) {
            return $fromSettings;
        }

        $candidates = [];
        if (PHP_BINARY && !str_contains(basename(PHP_BINARY), 'fpm')) {
            $candidates[] = PHP_BINARY;
        }
        $candidates[] = PHP_BINDIR . '/php';
        $candidates[] = PHP_BINDIR . '/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        foreach ($this->getPathDirs() as $dir) {
            $candidates[] = $dir . '/php';
            $candidates[] = $dir . '/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        }
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';

        foreach (array_unique($candidates) as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Команда запуска composer (бинарник или `php composer.phar`).
     *
     * @return string[]|null Массив частей команды или null, если не найден.
     */
    public function findComposer(): ?array
    {
        $fromSettings = (string)Yii::$app->settings->get('GENERAL', 'composer_path', '');
        if ($fromSettings !== '') {
            return $this->composerCommandFor($fromSettings);
        }

        $root = $this->getProjectRoot();
        $candidates = [
            $root . '/composer.phar',
            $root . '/composer',
        ];
        foreach ($this->getPathDirs() as $dir) {
            $candidates[] = $dir . '/composer';
            $candidates[] = $dir . '/composer.phar';
        }
        $candidates[] = '/usr/local/bin/composer';
        $candidates[] = '/usr/bin/composer';
        $home = getenv('HOME') ?: '';
        if ($home !== '') {
            $candidates[] = $home . '/composer.phar';
            $candidates[] = $home . '/bin/composer';
            $candidates[] = $home . '/.local/bin/composer';
        }

        foreach (array_unique($candidates) as $path) {
            if (is_file($path)) {
                $cmd = $this->composerCommandFor($path);
                if ($cmd !== null) {
                    return $cmd;
                }
            }
        }
        return null;
    }

    private function composerCommandFor(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        if (str_ends_with($path, '.phar') || !is_executable($path)) {
            $php = $this->findPhp();
            return $php ? [$php, $path] : null;
        }
        return [$path];
    }

    /**
     * @return string[]
     */
    private function getPathDirs(): array
    {
        $path = (string)(getenv('PATH') ?: '');
        $dirs = array_filter(explode(PATH_SEPARATOR, $path));
        return array_values(array_unique($dirs));
    }

    /**
     * Окружение для composer: без HOME composer отказывается работать,
     * а под FPM HOME обычно не задан.
     *
     * @return array<string, string>
     */
    public function getComposerEnv(): array
    {
        $home = Yii::getAlias('@runtime') . '/composer';
        FileHelper::createDirectory($home);

        return [
            'COMPOSER_HOME' => $home,
            'COMPOSER_ALLOW_SUPERUSER' => '1',
            'COMPOSER_NO_INTERACTION' => '1',
            'COMPOSER_MEMORY_LIMIT' => '-1',
        ];
    }

    // ------------------------------------------------------------------
    // Информация о пакете
    // ------------------------------------------------------------------

    /**
     * Ограничение версии пакета из composer.json проекта.
     */
    public function getConstraint(): ?string
    {
        $file = $this->getProjectRoot() . '/composer.json';
        if (!is_file($file)) {
            return null;
        }
        $json = json_decode((string)file_get_contents($file), true);
        return $json['require'][self::PACKAGE] ?? $json['require-dev'][self::PACKAGE] ?? null;
    }

    /**
     * Был ли vendor установлен без dev-зависимостей (composer 2 пишет это в installed.json).
     */
    public function isInstalledWithoutDev(): bool
    {
        $file = Yii::getAlias('@vendor') . '/composer/installed.json';
        if (!is_file($file)) {
            return false;
        }
        $json = json_decode((string)file_get_contents($file), true);
        return isset($json['dev']) && $json['dev'] === false;
    }

    /**
     * Грубая проверка: попадает ли свежий релиз в ограничение composer.json.
     * Сравнивает только мажорную версию для `^` и `~`.
     */
    public function constraintAllows(?string $constraint, ?string $latest): ?bool
    {
        if ($constraint === null || $latest === null) {
            return null;
        }
        $constraint = trim($constraint);
        if ($constraint === '*' || str_starts_with($constraint, 'dev-') || str_contains($constraint, '@dev')) {
            return true;
        }
        if (!preg_match('/^[\^~]?\s*v?(\d+)/', $constraint, $m)) {
            return null;
        }
        $major = (int)$m[1];
        $latestMajor = (int)explode('.', ltrim($latest, 'vV'))[0];
        if ($major === 0 && str_starts_with($constraint, '^')) {
            // ^0.x — composer трактует как ~0.x
            return $latestMajor === 0;
        }
        return $latestMajor === $major;
    }

    // ------------------------------------------------------------------
    // Проверки окружения
    // ------------------------------------------------------------------

    /**
     * Список проверок для запуска обновления из админки.
     *
     * @return array<int, array{key: string, ok: bool, label: string, hint: string}>
     */
    public function checkBackground(): array
    {
        $root = $this->getProjectRoot();
        $checks = [];

        $checks[] = [
            'key' => 'os',
            'ok' => PHP_OS_FAMILY !== 'Windows',
            'label' => 'Операционная система поддерживает фоновый запуск',
            'hint' => 'На Windows запустите обновление вручную: php yii admin/update',
        ];
        $checks[] = [
            'key' => 'proc_open',
            'ok' => function_exists('proc_open'),
            'label' => 'Функция proc_open доступна',
            'hint' => 'proc_open отключена в disable_functions. Запустите вручную или по cron.',
        ];
        $php = $this->findPhp();
        $checks[] = [
            'key' => 'php',
            'ok' => $php !== null,
            'label' => 'PHP CLI найден' . ($php ? ': ' . $php : ''),
            'hint' => 'Укажите путь к PHP CLI в настройке GENERAL → php_path.',
        ];
        $composer = $this->findComposer();
        $checks[] = [
            'key' => 'composer',
            'ok' => $composer !== null,
            'label' => 'Composer найден' . ($composer ? ': ' . implode(' ', $composer) : ''),
            'hint' => 'Укажите путь к composer или composer.phar в настройке GENERAL → composer_path.',
        ];
        $yii = $this->getYiiScript();
        $checks[] = [
            'key' => 'yii',
            'ok' => $yii !== null,
            'label' => 'Консольный скрипт yii найден' . ($yii ? ': ' . $yii : ''),
            'hint' => 'Скрипт yii не найден в ' . Yii::getAlias('@app') . ' и ' . $root,
        ];
        $checks[] = [
            'key' => 'composer_json',
            'ok' => is_file($root . '/composer.json'),
            'label' => 'composer.json найден в ' . $root,
            'hint' => 'Проект должен быть установлен через composer.',
        ];

        $writable = [
            $root . '/vendor',
            $root . '/vendor/composer',
            $root . '/vendor/mitisk/yii2-admin',
        ];
        if (is_file($root . '/composer.lock')) {
            $writable[] = $root . '/composer.lock';
        } else {
            $writable[] = $root;
        }
        $notWritable = array_values(array_filter($writable, static fn($p) => !is_writable($p)));
        $checks[] = [
            'key' => 'writable',
            'ok' => $notWritable === [],
            'label' => 'Права на запись в vendor и composer.lock',
            'hint' => 'Нет прав на запись: ' . implode(', ', $notWritable)
                . '. Выдайте права пользователю веб-сервера или запускайте обновление по cron от владельца файлов.',
        ];
        $checks[] = [
            'key' => 'runtime',
            'ok' => is_writable(Yii::getAlias('@runtime')),
            'label' => 'Каталог runtime доступен для записи лога',
            'hint' => 'Нет прав на запись в ' . Yii::getAlias('@runtime'),
        ];

        return $checks;
    }

    public function canRunInBackground(): bool
    {
        foreach ($this->checkBackground() as $check) {
            if (!$check['ok']) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Состояние
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $state = Yii::$app->settings->get(self::STATE_SECTION, self::STATE_KEY, []);
        if (!is_array($state)) {
            $state = [];
        }
        $state += [
            'status' => self::STATUS_IDLE,
            'started_at' => null,
            'finished_at' => null,
            'pid' => null,
            'log' => null,
            'message' => null,
            'exit_code' => null,
            'version_from' => null,
            'version_to' => null,
        ];

        // Зависшее обновление: процесс умер или прошло слишком много времени
        if (in_array($state['status'], [self::STATUS_STARTING, self::STATUS_RUNNING], true)) {
            $age = time() - (int)($state['started_at'] ?? 0);
            $dead = $state['pid'] && !$this->isProcessAlive((int)$state['pid']);
            if ($age > self::STALE_AFTER || ($dead && $age > 15)) {
                $state['status'] = self::STATUS_FAILED;
                $state['message'] = 'Процесс обновления завершился без результата (зависание или падение).';
                $state['finished_at'] = time();
                $this->saveState($state);
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function saveState(array $state): void
    {
        Yii::$app->settings->set(
            self::STATE_SECTION,
            self::STATE_KEY,
            json_encode($state, JSON_UNESCAPED_UNICODE),
            'json'
        );
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function updateState(array $patch): array
    {
        $state = array_merge($this->getState(), $patch);
        $this->saveState($state);
        return $state;
    }

    public function isRunning(): bool
    {
        return in_array($this->getState()['status'], [self::STATUS_STARTING, self::STATUS_RUNNING], true);
    }

    private function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }
        if (is_dir('/proc')) {
            return file_exists('/proc/' . $pid);
        }
        return true; // не умеем проверять — считаем живым
    }

    /**
     * Хвост лога обновления.
     */
    public function readLog(int $maxBytes = 200000): string
    {
        $log = $this->getState()['log'] ?? null;
        if (!$log || !is_file($log)) {
            return '';
        }
        $size = (int)filesize($log);
        $handle = fopen($log, 'rb');
        if (!$handle) {
            return '';
        }
        if ($size > $maxBytes) {
            fseek($handle, $size - $maxBytes);
        }
        $data = (string)stream_get_contents($handle);
        fclose($handle);
        return $data;
    }

    // ------------------------------------------------------------------
    // Запуск
    // ------------------------------------------------------------------

    /**
     * Запускает `php yii admin/update` отсоединённым фоновым процессом.
     *
     * @throws \RuntimeException если запуск невозможен.
     */
    public function startBackground(): array
    {
        if ($this->isRunning()) {
            throw new \RuntimeException('Обновление уже выполняется.');
        }
        foreach ($this->checkBackground() as $check) {
            if (!$check['ok']) {
                throw new \RuntimeException($check['hint']);
            }
        }

        $php = $this->findPhp();
        $yii = $this->getYiiScript();
        $logDir = Yii::getAlias('@runtime') . '/admin-update';
        FileHelper::createDirectory($logDir);
        $log = $logDir . '/' . date('Ymd_His') . '.log';
        file_put_contents($log, '[' . date('Y-m-d H:i:s') . "] Запуск обновления из админки...\n");

        $env = '';
        foreach ($this->getComposerEnv() as $name => $value) {
            $env .= $name . '=' . escapeshellarg($value) . ' ';
        }

        $inner = $env . escapeshellarg($php) . ' ' . escapeshellarg($yii) . ' admin/update --from-web=1';
        $cmd = 'nohup env ' . $inner . ' >> ' . escapeshellarg($log) . ' 2>&1 & echo $!';

        $this->saveState([
            'status' => self::STATUS_STARTING,
            'started_at' => time(),
            'finished_at' => null,
            'pid' => null,
            'log' => $log,
            'message' => null,
            'exit_code' => null,
            'version_from' => Module::VERSION,
            'version_to' => null,
        ]);

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'a'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes, $this->getProjectRoot());
        if (!is_resource($process)) {
            $this->updateState(['status' => self::STATUS_FAILED, 'message' => 'Не удалось запустить процесс.', 'finished_at' => time()]);
            throw new \RuntimeException('Не удалось запустить фоновый процесс.');
        }
        $pid = (int)trim((string)stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        proc_close($process);

        return $this->updateState(['pid' => $pid ?: null]);
    }

    /**
     * Полный цикл обновления. Вызывается из консольной команды.
     *
     * @param callable(string): void $out Приёмник строк лога.
     *
     * @return int Код завершения (0 — успех).
     */
    public function run(callable $out, bool $fromWeb = false): int
    {
        $lockFile = Yii::getAlias('@runtime') . '/admin-update.lock';
        FileHelper::createDirectory(dirname($lockFile));
        $lock = fopen($lockFile, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $out('Обновление уже выполняется (lock: ' . $lockFile . ').');
            return 2;
        }

        $state = $this->getState();
        if (!$fromWeb || $state['status'] !== self::STATUS_STARTING) {
            // Ручной запуск — заводим новое состояние
            $state = [
                'status' => self::STATUS_RUNNING,
                'started_at' => time(),
                'finished_at' => null,
                'pid' => getmypid() ?: null,
                'log' => null,
                'message' => null,
                'exit_code' => null,
                'version_from' => Module::VERSION,
                'version_to' => null,
            ];
            $this->saveState($state);
        } else {
            $this->updateState(['status' => self::STATUS_RUNNING, 'pid' => getmypid() ?: null]);
        }

        $exit = 1;
        try {
            $exit = $this->doRun($out);
        } catch (\Throwable $e) {
            $out('ОШИБКА: ' . $e->getMessage());
            $exit = 1;
        } finally {
            $final = $this->getState();
            $this->saveState(array_merge($final, [
                'status' => $exit === 0 ? self::STATUS_FINISHED : self::STATUS_FAILED,
                'finished_at' => time(),
                'exit_code' => $exit,
                'message' => $exit === 0
                    ? 'Обновление завершено.'
                    : ($final['message'] ?: 'Обновление завершилось с ошибкой (код ' . $exit . ').'),
            ]));
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $exit;
    }

    /**
     * @param callable(string): void $out
     */
    private function doRun(callable $out): int
    {
        $root = $this->getProjectRoot();
        $out('Корень проекта: ' . $root);
        $out('Текущая версия модуля: ' . Module::VERSION);

        $composer = $this->findComposer();
        if ($composer === null) {
            $this->updateState(['message' => 'Composer не найден. Укажите путь в настройке GENERAL → composer_path.']);
            $out('Composer не найден. Укажите путь в настройке GENERAL → composer_path.');
            return 1;
        }
        $php = $this->findPhp() ?: PHP_BINARY;
        $yii = $this->getYiiScript();
        if ($yii === null) {
            $out('Не найден консольный скрипт yii.');
            return 1;
        }

        $constraint = $this->getConstraint();
        $out('Ограничение в composer.json: ' . ($constraint ?? 'не найдено'));

        // 1. composer update
        $args = array_merge($composer, [
            'update', self::PACKAGE,
            '--with-dependencies',
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
            '--optimize-autoloader',
        ]);
        if ($this->isInstalledWithoutDev()) {
            $args[] = '--no-dev';
        }
        $out('');
        $out('> ' . implode(' ', $args));
        $code = $this->exec($args, $root, $this->getComposerEnv(), $out);
        if ($code !== 0) {
            $this->updateState(['message' => 'composer update завершился с кодом ' . $code . '.']);
            return $code;
        }

        // 2. Пост-шаги в новом процессе, чтобы загрузился уже обновлённый код
        $out('');
        $out('> ' . $php . ' ' . $yii . ' admin/update/finish');
        $code = $this->exec([$php, $yii, 'admin/update/finish'], $root, [], $out);
        if ($code !== 0) {
            $this->updateState(['message' => 'Миграции или пост-обработка завершились с кодом ' . $code . '.']);
        }
        return $code;
    }

    /**
     * Пост-шаги после замены файлов: миграции, кэш, версия.
     * Выполняется в свежем процессе (`admin/update/finish`).
     *
     * @param callable(string): void $out
     */
    public function finish(callable $out): int
    {
        $out('Установленная версия модуля: ' . Module::VERSION);

        $out('Применяю миграции модуля...');
        $migrationPath = Yii::getAlias('@vendor/mitisk/yii2-admin/migrations');
        $result = Yii::$app->runAction('migrate/up', [
            'migrationPath' => $migrationPath,
            'migrationNamespaces' => [],
            'interactive' => false,
        ]);
        if (is_int($result) && $result !== 0) {
            $out('Миграции завершились с кодом ' . $result . '.');
            return $result;
        }

        try {
            Yii::$app->cache->flush();
            $out('Кэш приложения очищен.');
        } catch (\Throwable $e) {
            $out('Не удалось очистить кэш: ' . $e->getMessage());
        }
        if (Yii::$app->authManager && method_exists(Yii::$app->authManager, 'invalidateCache')) {
            Yii::$app->authManager->invalidateCache();
        }

        Yii::$app->settings->set('GENERAL', 'version', Module::VERSION, 'string');
        $this->updateState(['version_to' => Module::VERSION]);
        $out('Версия ' . Module::VERSION . ' сохранена в настройках.');
        return 0;
    }

    /**
     * Запускает процесс и построчно передаёт вывод в $out.
     *
     * @param string[]              $args
     * @param array<string, string> $env
     * @param callable(string): void $out
     */
    private function exec(array $args, string $cwd, array $env, callable $out): int
    {
        $cmd = implode(' ', array_map('escapeshellarg', $args));
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $fullEnv = array_merge(getenv() ?: [], $env);
        $process = proc_open($cmd, $descriptors, $pipes, $cwd, $fullEnv);
        if (!is_resource($process)) {
            $out('Не удалось запустить: ' . $cmd);
            return 1;
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffers = ['', ''];
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 1);
            if ($changed === false) {
                break;
            }
            foreach ($read as $stream) {
                $idx = $stream === $pipes[1] ? 0 : 1;
                $chunk = (string)fread($stream, 8192);
                if ($chunk === '') {
                    continue;
                }
                $buffers[$idx] .= $chunk;
                while (($pos = strpos($buffers[$idx], "\n")) !== false) {
                    $out(rtrim(substr($buffers[$idx], 0, $pos), "\r"));
                    $buffers[$idx] = substr($buffers[$idx], $pos + 1);
                }
            }
            $status = proc_get_status($process);
            if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
        }
        foreach ($buffers as $rest) {
            if ($rest !== '') {
                $out(rtrim($rest, "\r"));
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process);
    }
}
