<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\AuditLog;
use yii\base\Widget;

/**
 * Виджет аудит-лога и счётчиков ошибок на дашборде.
 */
final class IndexAdminLogWidget extends Widget
{
    /** @var string Путь к лог-файлу */
    private string $logFile = '@runtime/logs/app.log';

    /**
     * {@inheritdoc}
     */
    public function run(): string
    {
        if (!\Yii::$app->user->can('viewReports')) {
            return '';
        }

        $todayStart = strtotime('today');
        $weekStart = strtotime('-7 days');

        // Счётчики из БД
        $actionsToday = AuditLog::find()
            ->where(['>=', 'created_at', $todayStart])
            ->count();

        // Счётчики ошибок из файла
        $file = \Yii::getAlias($this->logFile);
        $logLines = $this->tail($file, 2000);
        $parsed = array_filter(array_map(
            [$this, 'parseLine'],
            $logLines
        ));

        $errorsToday = 0;
        $errorsWeek = 0;
        foreach ($parsed as $row) {
            $ts = strtotime($row['datetime']);
            if (!$ts) {
                continue;
            }
            if ($row['level'] === 'error') {
                if ($ts >= $todayStart) {
                    $errorsToday++;
                }
                if ($ts >= $weekStart) {
                    $errorsWeek++;
                }
            }
        }

        // Последние действия из БД
        $recentActions = AuditLog::find()
            ->with('user')
            ->orderBy(['id' => SORT_DESC])
            ->limit(7)
            ->all();

        return $this->render('index/admin_log', [
            'actionsToday' => $actionsToday,
            'errorsToday' => $errorsToday,
            'errorsWeek' => $errorsWeek,
            'recentActions' => $recentActions,
        ]);
    }

    /**
     * Читает последние N строк файла.
     *
     * @param string $filepath Путь к файлу
     * @param int    $lines    Количество строк
     *
     * @return array
     */
    private function tail(string $filepath, int $lines = 10): array
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return [];
        }
        $f = fopen($filepath, 'rb');
        if (!$f) {
            return [];
        }

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

    /**
     * Парсит строку лога.
     *
     * @param string $line Строка лога
     *
     * @return array|null
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $re = '/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]\s*(.*)$/u';

        if (preg_match($re, $line, $m)) {
            return [
                'datetime' => $m[1],
                'ip' => $m[2] !== '' ? $m[2] : '-',
                'level' => $m[5],
                'message' => mb_strimwidth($m[7], 0, 250, '...'),
            ];
        }

        // Альтернативный формат (5 блоков)
        $re2 = '/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]\s*(.*)$/u';

        if (preg_match($re2, $line, $m)) {
            return [
                'datetime' => $m[1],
                'ip' => $m[2] !== '' ? $m[2] : '-',
                'level' => $m[5],
                'message' => mb_strimwidth($m[6], 0, 250, '...'),
            ];
        }

        return null;
    }
}
