<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\AdminUser;
use yii\base\Widget;

final class IndexAdminLogWidget extends Widget
{
    private string $logFile = '@runtime/logs/app.log';

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        if (!\Yii::$app->user->can('viewReports')) {
            return '';
        }

        $file = \Yii::getAlias($this->logFile);
        $lines = $this->tail($file, 500);
        $items = array_map([$this, 'parseLine'], $lines);
        // фильтруем нераспознанные строки
        $items = array_values(array_filter($items, fn($r) => $r !== null));

        return $this->render('index/admin_log', [
            'items' => $items
        ]);
    }

    private function tail(string $filepath, int $lines = 10): array
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return [];
        }
        $f = fopen($filepath, 'rb');
        if (!$f) return [];

        $buffer = '';
        $chunkSize = 4096;
        fseek($f, 0, SEEK_END);
        $pos = ftell($f);

        $lineCount = 0;
        while ($pos > 0 && $lineCount <= $lines) {
            $read = min($chunkSize, $pos);
            $pos -= $read;
            fseek($f, $pos);
            $buffer = fread($f, $read) . $buffer;
            $lineCount = substr_count($buffer, "\n");
        }
        fclose($f);

        $rows = explode("\n", rtrim($buffer, "\n"));
        return array_slice($rows, -$lines);
    }

    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') return null;

        // Ожидаемый формат:
        // 2025-10-18 20:41:55 [127.0.0.1][1][sessionId][error][category] Сообщение ...
        $re = '/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\s*(.*)$/u';
        if (preg_match($re, $line, $m)) {
            $datetime = $m[1] ?? '-';
            $ip = $m[2] ?? '-';
            $level = $m[5] ?? '-';
            $message = $m[7] ?? '';

            // Укорачиваем сообщение и убираем хвост стека до первой закрывающей скобки пути, чтобы было кратко
            // Например: "Unable to resolve the request "assets/...". in D:\...:561"
            $short = mb_strimwidth($message, 0, 250, '...');
            return [
                'datetime' => $datetime,
                'ip' => $ip !== '' ? $ip : '-',
                'level' => $level,
                'message' => $short,
            ];
        }

        // Альтернативный формат (без UID/SID) на всякий случай:
        $re2 = '/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\[([^\]]*)\]\s*(.*)$/u';
        if (preg_match($re2, $line, $m)) {
            $datetime = $m[1] ?? '-';
            $ip = $m[2] ?? '-';
            $level = $m[5] ?? '-';
            $message = $m[6] ?? '';
            $short = mb_strimwidth($message, 0, 250, '...');
            return [
                'datetime' => $datetime,
                'ip' => $ip !== '' ? $ip : '-',
                'level' => $level,
                'message' => $short,
            ];
        }

        // Если строка не соответствует — пропускаем
        return null;
    }
}
