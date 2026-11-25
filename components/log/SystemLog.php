<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\log;

use Yii;
use yii\log\FileTarget;

/**
 * Чтение лога приложения для раздела «Системный лог»: текущий файл и архивные (`app.log.1`, …),
 * от новых к старым, пока не набран предел объёма. Читается хвост — в админке важны последние записи.
 */
final class SystemLog
{
    /** Сколько байт лога читается за один показ. */
    public const BUDGET = 4 * 1024 * 1024;

    private string $file;
    private int $budget;

    public function __construct(string $file, int $budget = self::BUDGET)
    {
        $this->file = $file;
        $this->budget = max(1024, $budget);
    }

    /** Файл первого FileTarget приложения; нет такого — runtime/logs/app.log. */
    public static function fromApp(): self
    {
        $file = Yii::getAlias('@runtime/logs/app.log');
        try {
            foreach (Yii::$app->getLog()->targets as $target) {
                if ($target instanceof FileTarget && $target->logFile) {
                    $file = Yii::getAlias($target->logFile);
                    break;
                }
            }
        } catch (\Throwable) {
            // Лог не настроен — остаётся путь по умолчанию
        }

        return new self((string) $file);
    }

    /**
     * @return array{chunks: string[], files: int, size: int, read: int, truncated: bool}
     *         chunks — куски текста от старых к новым, каждый начинается с целой записи
     */
    public function read(): array
    {
        $chunks = [];
        $left = $this->budget;
        $size = 0;
        $read = 0;
        $files = 0;

        foreach ($this->files() as $path) {
            $bytes = (int) @filesize($path);
            $size += $bytes;
            if ($left <= 0 || $bytes === 0) {
                continue;
            }
            $chunk = $this->tail($path, $bytes, $left);
            if ($chunk === '') {
                continue;
            }
            $files++;
            $read += strlen($chunk);
            $left -= strlen($chunk);
            array_unshift($chunks, $chunk);
        }

        return [
            'chunks' => $chunks,
            'files' => $files,
            'size' => $size,
            'read' => $read,
            'truncated' => $read < $size,
        ];
    }

    /**
     * @return string[] существующие файлы лога, от нового к старому
     */
    public function files(): array
    {
        $files = [];
        if (is_file($this->file) && is_readable($this->file)) {
            $files[] = $this->file;
        }
        for ($i = 1; $i <= 50; $i++) {
            $rotated = $this->file . '.' . $i;
            if (!is_file($rotated)) {
                break;
            }
            if (is_readable($rotated)) {
                $files[] = $rotated;
            }
        }

        return $files;
    }

    private function tail(string $path, int $bytes, int $limit): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $length = min($bytes, $limit);
        fseek($handle, $bytes - $length);
        $text = (string) stream_get_contents($handle, $length);
        fclose($handle);

        if ($length < $bytes) {
            // Начали с середины: отбрасываем всё до первой целой записи
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)? \[/m', $text, $m, PREG_OFFSET_CAPTURE) !== 1) {
                return '';
            }
            $text = substr($text, $m[0][1]);
        }

        return $text;
    }
}
