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
        $running = [];
        $results = [];
        foreach ($jobs as $id => $job) {
            $pipes = [];
            $process = @proc_open(
                array_merge([$this->php, $this->script], $job['command']),
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname($this->script)
            );
            if (!is_resource($process)) {
                $results[$id] = ['exit' => null, 'output' => 'Не удалось запустить процесс: проверьте, что функция proc_open разрешена.', 'duration' => 0, 'timedOut' => false];
                continue;
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $running[$id] = ['process' => $process, 'pipes' => $pipes, 'started' => microtime(true), 'timeout' => max(1, (int) $job['timeout']), 'output' => ''];
        }

        while ($running !== []) {
            foreach ($running as $id => &$item) {
                foreach ([1, 2] as $pipe) {
                    $chunk = stream_get_contents($item['pipes'][$pipe]);
                    if (is_string($chunk) && $chunk !== '') {
                        $item['output'] = substr($item['output'] . $chunk, -self::OUTPUT_BUFFER);
                    }
                }
                $status = proc_get_status($item['process']);
                $elapsed = microtime(true) - $item['started'];
                $timedOut = $status['running'] && $elapsed > $item['timeout'];
                if ($status['running'] && !$timedOut) {
                    continue;
                }
                if ($timedOut) {
                    proc_terminate($item['process']);
                }
                foreach ([1, 2] as $pipe) {
                    $rest = stream_get_contents($item['pipes'][$pipe]);
                    if (is_string($rest) && $rest !== '') {
                        $item['output'] = substr($item['output'] . $rest, -self::OUTPUT_BUFFER);
                    }
                    fclose($item['pipes'][$pipe]);
                }
                $exit = $status['running'] ? null : (int) $status['exitcode'];
                proc_close($item['process']);
                $results[$id] = [
                    'exit' => $timedOut ? null : $exit,
                    'output' => $item['output'],
                    'duration' => (int) round($elapsed * 1000),
                    'timedOut' => $timedOut,
                ];
                unset($running[$id]);
            }
            unset($item);
            if ($running !== []) {
                usleep(100000);
            }
        }

        return $results;
    }
}
