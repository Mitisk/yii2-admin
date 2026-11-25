<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\cron;

/**
 * Расписание в формате cron: пять полей — минута, час, день месяца, месяц, день недели.
 * В поле допустимы «*», число, список через запятую, диапазон «a-b» и шаг «* /n» или «a-b/n».
 * День недели: 0 и 7 — воскресенье. Имена месяцев и дней (JAN, MON) не поддерживаются.
 *
 * Время — по часовому поясу PHP (date_default_timezone_get()), то есть по поясу приложения.
 */
final class CronExpression
{
    /** Границы полей: минута, час, день месяца, месяц, день недели */
    private const RANGES = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];

    /** Сколько минут вперёд искать следующий запуск (чуть больше года: «29 февраля» найдётся не всегда) */
    private const SEARCH_MINUTES = 527040;

    public static function isValid(string $expression): bool
    {
        return self::parse($expression) !== null;
    }

    /**
     * Пора ли запускать в минуту, которой принадлежит $time.
     */
    public static function isDue(string $expression, int $time): bool
    {
        $fields = self::parse($expression);

        return $fields !== null && self::matches($fields, $time);
    }

    /**
     * Ближайший запуск строго после минуты $from или null, если расписание неверно или не наступит за год.
     */
    public static function nextRun(string $expression, int $from): ?int
    {
        $fields = self::parse($expression);
        if ($fields === null) {
            return null;
        }
        $time = $from - $from % 60;
        for ($i = 0; $i < self::SEARCH_MINUTES; $i++) {
            $time += 60;
            // Быстрый пропуск часа и суток, которые заведомо не подходят
            if (!isset($fields[1][(int) date('G', $time)])) {
                $time += (59 - (int) date('i', $time)) * 60;
                continue;
            }
            if (self::matches($fields, $time)) {
                return $time;
            }
        }

        return null;
    }

    /**
     * Расписание словами для частых случаев; для остальных — само выражение.
     */
    public static function describe(string $expression): string
    {
        $expression = trim((string) preg_replace('/\s+/', ' ', $expression));
        if (!self::isValid($expression)) {
            return $expression;
        }
        [$minute, $hour, $day, $month, $weekday] = explode(' ', $expression);
        $anyDate = $day === '*' && $month === '*';

        if ($expression === '* * * * *') {
            return 'каждую минуту';
        }
        if ($anyDate && $weekday === '*' && $hour === '*' && preg_match('~^\*/(\d+)$~', $minute, $m)) {
            return 'каждые ' . $m[1] . ' ' . self::plural((int) $m[1], 'минуту', 'минуты', 'минут');
        }
        if ($anyDate && $weekday === '*' && $hour === '*' && ctype_digit($minute)) {
            return 'каждый час в :' . str_pad($minute, 2, '0', STR_PAD_LEFT);
        }
        if ($anyDate && $weekday === '*' && ctype_digit($minute) && preg_match('~^\*/(\d+)$~', $hour, $m)) {
            return 'каждые ' . $m[1] . ' ' . self::plural((int) $m[1], 'час', 'часа', 'часов') . ' в :' . str_pad($minute, 2, '0', STR_PAD_LEFT);
        }
        if (ctype_digit($minute) && ctype_digit($hour)) {
            $at = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minute, 2, '0', STR_PAD_LEFT);
            if ($anyDate && $weekday === '*') {
                return 'каждый день в ' . $at;
            }
            if ($anyDate && ctype_digit($weekday)) {
                $days = ['по воскресеньям', 'по понедельникам', 'по вторникам', 'по средам', 'по четвергам', 'по пятницам', 'по субботам', 'по воскресеньям'];
                return $days[(int) $weekday] . ' в ' . $at;
            }
            if ($anyDate && $weekday === '1-5') {
                return 'по будням в ' . $at;
            }
            if (ctype_digit($day) && $month === '*' && $weekday === '*') {
                return $day . '-го числа каждого месяца в ' . $at;
            }
        }

        return $expression;
    }

    /**
     * @return array<int, array<int, true>>|null для каждого поля — множество подходящих значений
     */
    private static function parse(string $expression): ?array
    {
        $parts = preg_split('/\s+/', trim($expression));
        if ($parts === false || count($parts) !== 5) {
            return null;
        }

        $fields = [];
        foreach ($parts as $index => $part) {
            [$min, $max] = self::RANGES[$index];
            $values = [];
            foreach (explode(',', $part) as $piece) {
                if (!preg_match('~^(\*|\d+(?:-\d+)?)(?:/(\d+))?$~', $piece, $m)) {
                    return null;
                }
                $step = isset($m[2]) ? (int) $m[2] : 1;
                if ($step < 1) {
                    return null;
                }
                if ($m[1] === '*') {
                    [$from, $to] = [$min, $max];
                } elseif (str_contains($m[1], '-')) {
                    [$from, $to] = array_map('intval', explode('-', $m[1]));
                } else {
                    $from = (int) $m[1];
                    // «5/10» — с пятой и далее с шагом; без шага — одно значение
                    $to = isset($m[2]) ? $max : $from;
                }
                if ($from < $min || $to > $max || $from > $to) {
                    return null;
                }
                for ($value = $from; $value <= $to; $value += $step) {
                    $values[$value] = true;
                }
            }
            $fields[$index] = $values;
        }
        // Воскресенье — и 0, и 7
        if (isset($fields[4][7])) {
            $fields[4][0] = true;
        }
        // Звёздочка в дне месяца или дне недели: как в cron, заданное поле сужает, оба заданных — «или»
        $fields['domAny'] = $parts[2] === '*' || str_starts_with($parts[2], '*/');
        $fields['dowAny'] = $parts[4] === '*' || str_starts_with($parts[4], '*/');

        return $fields;
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function matches(array $fields, int $time): bool
    {
        [$minute, $hour, $day, $month, $weekday] = array_map('intval', explode(' ', date('i G j n w', $time)));
        if (!isset($fields[0][$minute], $fields[1][$hour], $fields[3][$month])) {
            return false;
        }
        $dayFits = isset($fields[2][$day]);
        $weekdayFits = isset($fields[4][$weekday]);
        if ($fields['domAny'] || $fields['dowAny']) {
            return $dayFits && $weekdayFits;
        }

        return $dayFits || $weekdayFits;
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }

        return $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) ? $few : $many;
    }
}
