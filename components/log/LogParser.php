<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\log;

/**
 * Разбор файла лога Yii (FileTarget) в записи и сборка одинаковых записей в «проблемы».
 *
 * Запись — строка с датой и префиксом `[ip][пользователь][сессия][уровень][категория]` плюс всё,
 * что идёт до следующей такой строки (стек). Дамп окружения запроса (`$_GET = […]`, пишется при
 * включённом logVars) отдельной записью не показывается: из него берётся адрес страницы для
 * записей того же запроса, остальное (cookie, сессия, POST) в админку не уходит.
 */
final class LogParser
{
    public const LEVELS = ['error', 'warning', 'info', 'trace', 'profile', 'profile begin', 'profile end'];

    /** Сколько последних случаев хранит группа. */
    public const SAMPLES = 5;

    /** Предел текста стека в ответе, символов. */
    public const FULL_LIMIT = 6000;

    private const HEADER = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.\d+)? ((?:\[[^\]\r\n]*\])+) ?(.*)$/u';

    private string $basePath;

    /**
     * @param string $basePath Корень проекта: пути в сообщениях показываются от него
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    }

    /**
     * @return array<int, array<string, mixed>> записи от старых к новым
     */
    public function parse(string $text): array
    {
        $entries = [];
        $current = null;
        // Записи одного запроса идут подряд и заканчиваются дампом окружения: адрес из него — им всем
        $batchStart = 0;

        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $line) {
            $header = $this->header($line);
            if ($header === null) {
                if ($current !== null) {
                    $current['lines'][] = rtrim($line);
                }
                continue;
            }
            if ($current !== null) {
                $this->push($entries, $current, $batchStart);
            }
            $current = $header;
        }
        if ($current !== null) {
            $this->push($entries, $current, $batchStart);
        }

        return $entries;
    }

    /**
     * Одинаковые записи — одной строкой со счётчиком, новые сверху.
     *
     * @param array<int, array<string, mixed>> $entries от старых к новым
     * @return array<int, array<string, mixed>>
     */
    public function groups(array $entries, ?int $now = null): array
    {
        $dayStart = strtotime('today', $now ?? time());
        $groups = [];
        foreach ($entries as $entry) {
            $key = (string) $entry['key'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => substr(md5($key), 0, 12),
                    'level' => $entry['level'],
                    'category' => $entry['category'],
                    'code' => $entry['code'],
                    'noise' => $entry['noise'],
                    'count' => 0,
                    'today' => 0,
                    'first' => $entry['ts'],
                    'samples' => [],
                ];
            }
            $group = &$groups[$key];
            $group['count']++;
            if ($entry['ts'] >= $dayStart) {
                $group['today']++;
            }
            // Заголовок и стек — от последнего случая
            $group['last'] = $entry['ts'];
            $group['title'] = $entry['title'];
            $group['class'] = $entry['class'];
            $group['where'] = $entry['where'];
            $group['full'] = $entry['full'];
            $group['samples'][] = [
                'ts' => $entry['ts'],
                'ip' => $entry['ip'],
                'user' => $entry['user'],
                'url' => $entry['url'],
                'method' => $entry['method'],
            ];
            if (count($group['samples']) > self::SAMPLES) {
                array_shift($group['samples']);
            }
            unset($group);
        }

        $groups = array_values($groups);
        foreach ($groups as &$group) {
            $group['samples'] = array_reverse($group['samples']);
        }
        unset($group);
        usort($groups, static fn (array $a, array $b): int => [$b['last'], $b['count']] <=> [$a['last'], $a['count']]);

        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function header(string $line): ?array
    {
        if (!isset($line[19]) || preg_match(self::HEADER, $line, $m) !== 1) {
            return null;
        }
        preg_match_all('/\[([^\]]*)\]/', $m[2], $blocks);
        $blocks = $blocks[1];

        // Уровень — первый блок с известным словом, категория — следующий; до них — префикс запроса
        $levelAt = null;
        foreach ($blocks as $i => $block) {
            if (in_array($block, self::LEVELS, true)) {
                $levelAt = $i;
                break;
            }
        }
        if ($levelAt === null) {
            return null;
        }
        $prefix = array_slice($blocks, 0, $levelAt);
        $rest = array_slice($blocks, $levelAt + 2);
        $message = $m[3];
        if ($rest !== []) {
            // Сообщение само начиналось с «[…]» — возвращаем съеденные блоки
            $message = '[' . implode('][', $rest) . ']' . ($message !== '' ? ' ' . $message : '');
        }

        return [
            'ts' => (int) strtotime($m[1]),
            'ip' => $this->prefixValue($prefix[0] ?? ''),
            'user' => $this->prefixValue($prefix[1] ?? ''),
            // Сам идентификатор сессии не нужен и наружу не уходит: по отпечатку сверяются записи одного запроса
            'session' => $this->prefixValue($prefix[2] ?? '') === '' ? '' : substr(md5($prefix[2]), 0, 8),
            'level' => $blocks[$levelAt],
            'category' => $blocks[$levelAt + 1] ?? '',
            'message' => $message,
            'lines' => [],
        ];
    }

    private function prefixValue(string $value): string
    {
        return $value === '-' ? '' : $value;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, mixed> $raw
     */
    private function push(array &$entries, array $raw, int &$batchStart): void
    {
        if (str_starts_with($raw['message'], '$_')) {
            $this->applyContext($entries, $raw, $batchStart);
            $batchStart = count($entries);

            return;
        }
        $entries[] = $this->entry($raw);
    }

    /**
     * Адрес и метод запроса из дампа $_SERVER — записям этого же запроса.
     *
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, mixed> $context
     */
    private function applyContext(array &$entries, array $context, int $batchStart): void
    {
        $dump = $context['message'] . "\n" . implode("\n", $context['lines']);
        $url = preg_match("/'REQUEST_URI' => '((?:[^'\\\\]|\\\\.)*)'/", $dump, $m) === 1 ? stripslashes($m[1]) : '';
        $method = preg_match("/'REQUEST_METHOD' => '([A-Z]+)'/", $dump, $m) === 1 ? $m[1] : '';
        if ($url === '') {
            return;
        }
        $url = LogMask::mask($url);
        for ($i = count($entries) - 1; $i >= $batchStart; $i--) {
            $same = $entries[$i]['ip'] === $context['ip']
                && $entries[$i]['user'] === $context['user']
                && $entries[$i]['session'] === $context['session'];
            if (!$same) {
                break;
            }
            $entries[$i]['url'] = $url;
            $entries[$i]['method'] = $method;
        }
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function entry(array $raw): array
    {
        $lines = $raw['lines'];
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }
        $first = LogMask::mask($this->relative((string) $raw['message']));
        $full = $lines === [] ? '' : LogMask::mask($this->relative(implode("\n", $lines)));
        if (mb_strlen($full) > self::FULL_LIMIT) {
            $full = mb_substr($full, 0, self::FULL_LIMIT) . "\n…";
        }

        $class = '';
        $where = '';
        $title = $first;
        if (preg_match('/^((?:\w+\\\\)*\w*(?:Exception|Error)\w*): (.*?)(?: in (\S.*?):(\d+))?$/u', $first, $m) === 1) {
            $class = (string) (strrchr('\\' . $m[1], '\\') ?: $m[1]);
            $class = ltrim($class, '\\');
            $title = trim($m[2]) !== '' ? trim($m[2]) : $class;
            $where = isset($m[3]) ? $m[3] . ':' . $m[4] : '';
        }

        $code = preg_match('/HttpException:(\d{3})$/', (string) $raw['category'], $m) === 1 ? (int) $m[1] : null;

        return [
            'ts' => $raw['ts'],
            'ip' => $raw['ip'],
            'user' => $raw['user'],
            'session' => $raw['session'],
            'level' => $raw['level'],
            'category' => $raw['category'],
            'code' => $code,
            // Ошибки посетителей (не найдено, нет доступа, слишком часто) — не поломка сайта
            'noise' => $code !== null && $code >= 400 && $code < 500,
            'title' => mb_strlen($title) > 300 ? mb_substr($title, 0, 300) . '…' : $title,
            'class' => $class,
            'where' => $where,
            'full' => $full,
            'url' => '',
            'method' => '',
            'key' => $raw['level'] . '|' . $raw['category'] . '|' . $class . '|' . $this->normalize($title) . '|' . $where,
        ];
    }

    /** Текст без того, что меняется от случая к случаю: числа, хеши, скрытые ключи, запрошенный адрес. */
    private function normalize(string $title): string
    {
        $title = (string) preg_replace('/(Unable to resolve the request:? ?)"[^"]*"/u', '$1"…"', $title);
        $title = (string) preg_replace('/\S*••••/u', '•', $title);
        $title = (string) preg_replace('/\b[0-9a-f]{8,}\b/i', '…', $title);

        return (string) preg_replace('/\d+/', '#', $title);
    }

    private function relative(string $text): string
    {
        if ($this->basePath === '') {
            return $text;
        }
        $windows = str_replace('/', '\\', $this->basePath);

        return str_ireplace(
            [$this->basePath . '/', $windows . '\\', $this->basePath, $windows],
            '',
            $text
        );
    }
}
