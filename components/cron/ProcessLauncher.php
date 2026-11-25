<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\cron;

/**
 * Запускает задачи планировщика отдельными процессами `php yii <маршрут> <аргументы>` и ждёт их.
 *
 * Команда передаётся массивом, без командной оболочки: в аргументах нечему «выполниться».
 * Задачи одной минуты идут параллельно; у каждой свой лимит времени, по истечении процесс завершается.
 */
final class ProcessLauncher
{
    /** Сколько байт вывода держать в памяти на задачу (журнал обрежет ещё раз) */
    private const OUTPUT_BUFFER = 65536;

    /**
     * @param string $php путь к PHP CLI
     * @param string $script точка входа консоли (файл yii проекта)
     */
    public function __construct(private readonly string $php, private readonly string $script)
    {
    }

    /**
     * @param array<int, array{command: string[], timeout: int}> $jobs по id задачи
     * @return array<int, array{exit: int|null, output: string, duration: int, timedOut: bool}>
     */
    public function __invoke(array $jobs): array
    {
        $raw = [];
        foreach ($jobs as $id => $job) {
            $raw[$id] = ['args' => array_merge([$this->script], $job['command']), 'timeout' => (int) $job['timeout']];
        }

        return $this->runRaw($raw);
    }

    /**
     * Запускает `php <args>` для каждой задачи и ждёт всех.
     *
     * @param array<int, array{args: string[], timeout: int}> $jobs timeout 0 — без лимита времени
     * @return array<int, array{exit: int|null, output: string, duration: int, timedOut: bool}>
     */
    private function runRaw(array $jobs): array
    {
        $running = [];
        $results = [];
        foreach ($jobs as $id => $job) {
            // Вывод — во временный файл, а не в канал: канал не переполнится у болтливой задачи, а опрос
            // процесса не ждёт чтения (на Windows каналы не бывают неблокирующими — лимит времени не работал бы)
            $outputFile = tempnam(sys_get_temp_dir(), 'admin-cron-');
            $output = $outputFile !== false ? @fopen($outputFile, 'w+') : false;
            if ($output === false) {
                $results[$id] = ['exit' => null, 'output' => 'Не удалось создать временный файл для вывода задачи.', 'duration' => 0, 'timedOut' => false];
                continue;
            }
            $pipes = [];
            $process = @proc_open(
                array_merge([$this->php], $job['args']),
                [0 => ['pipe', 'r'], 1 => $output, 2 => $output],
                $pipes,
                dirname($this->script)
            );
            if (!is_resource($process)) {
                fclose($output);
                @unlink($outputFile);
                $results[$id] = ['exit' => null, 'output' => 'Не удалось запустить процесс: проверьте, что функция proc_open разрешена.', 'duration' => 0, 'timedOut' => false];
                continue;
            }
            fclose($pipes[0]);
            $running[$id] = [
                'process' => $process,
                'output' => $output,
                'outputFile' => $outputFile,
                'started' => microtime(true),
                'timeout' => max(0, (int) $job['timeout']),
            ];
        }

        while ($running !== []) {
            foreach ($running as $id => $item) {
                $status = proc_get_status($item['process']);
                $elapsed = microtime(true) - $item['started'];
                $timedOut = $status['running'] && $item['timeout'] > 0 && $elapsed > $item['timeout'];
                if ($status['running'] && !$timedOut) {
                    continue;
                }
                if ($timedOut) {
                    proc_terminate($item['process']);
                }
                // Код выхода — из этого proc_get_status(): в PHP < 8.3 proc_close() после него вернёт -1
                $exit = $status['running'] ? null : (int) $status['exitcode'];
                proc_close($item['process']);
                $results[$id] = [
                    'exit' => $timedOut ? null : $exit,
                    'output' => self::tail($item['output']),
                    'duration' => (int) round($elapsed * 1000),
                    'timedOut' => $timedOut,
                ];
                fclose($item['output']);
                @unlink($item['outputFile']);
                unset($running[$id]);
            }
            if ($running !== []) {
                usleep(100000);
            }
        }

        return $results;
    }

    /**
     * Конец вывода задачи: последние OUTPUT_BUFFER байт.
     *
     * @param resource $handle
     */
    private static function tail($handle): string
    {
        fflush($handle);
        $size = (int) (fstat($handle)['size'] ?? 0);
        fseek($handle, max(0, $size - self::OUTPUT_BUFFER));

        return (string) stream_get_contents($handle);
    }
}
