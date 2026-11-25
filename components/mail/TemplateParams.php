<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\mail;

/**
 * Переменные шаблона письма (`email_templates.params`): чистка того, что пришло из формы,
 * соединение с переменными события из кода и значения-примеры для предпросмотра и тестового письма.
 *
 * Формат: ['ИМЯ' => ['desc' => 'Описание', 'required' => '1'|'0', 'html' => '1'|'0', 'sample' => 'Пример']].
 * Чистый класс без обращений к БД и приложению.
 */
final class TemplateParams
{
    public const DESC_MAX = 255;
    public const SAMPLE_MAX = 2000;

    /**
     * Имена переменных в тексте, в порядке появления. `{{content}}` — метка макета, не переменная.
     *
     * @return string[]
     */
    public static function names(string ...$texts): array
    {
        $names = [];
        foreach ($texts as $text) {
            $text = (string) preg_replace('/%7B%7B(\w+)%7D%7D/i', '{{$1}}', $text);
            if (preg_match_all('/\{\{(\w+)\}\}/', $text, $m)) {
                foreach ($m[1] as $name) {
                    $names[$name] = true;
                }
            }
        }
        unset($names['content']);

        return array_map('strval', array_keys($names));
    }

    /**
     * Переменные из формы: только имена из букв, цифр и подчёркивания, строки обрезаны, флаги — '0' / '1'.
     *
     * @return array<string, array{desc: string, required: string, html: string, sample: string}>
     */
    public static function normalize(mixed $input): array
    {
        $params = [];
        foreach (is_array($input) ? $input : [] as $name => $row) {
            $name = (string) $name;
            if (preg_match('/^\w+$/', $name) !== 1 || $name === 'content') {
                continue;
            }
            $row = is_array($row) ? $row : [];
            $params[$name] = [
                'desc' => mb_substr(trim((string) ($row['desc'] ?? '')), 0, self::DESC_MAX),
                'required' => !empty($row['required']) ? '1' : '0',
                'html' => !empty($row['html']) ? '1' : '0',
                'sample' => mb_substr((string) ($row['sample'] ?? ''), 0, self::SAMPLE_MAX),
            ];
        }

        return $params;
    }

    /**
     * Переменные для формы: из кода события (их флаги «обязательно» и «HTML» владелец не снимает),
     * сохранённые в шаблоне и найденные в тексте.
     *
     * @param array<string, mixed> $saved сохранённые params шаблона
     * @param array<string, mixed> $code params() события из кода
     * @param string[] $used имена, которые есть в теме или теле
     * @return array<string, array{desc: string, required: string, html: string, sample: string, code: bool, lockRequired: bool, lockHtml: bool, used: bool}>
     */
    public static function rows(array $saved, array $code, array $used): array
    {
        $saved = self::normalize($saved);
        $code = self::normalize($code);
        $rows = [];
        foreach (array_unique(array_merge(array_keys($code), $used, array_keys($saved))) as $name) {
            $name = (string) $name;
            $own = $saved[$name] ?? ['desc' => '', 'required' => '0', 'html' => '0', 'sample' => ''];
            $fromCode = $code[$name] ?? null;
            $rows[$name] = [
                'desc' => $own['desc'] !== '' ? $own['desc'] : ($fromCode['desc'] ?? ''),
                'required' => $own['required'] === '1' || ($fromCode['required'] ?? '0') === '1' ? '1' : '0',
                'html' => $own['html'] === '1' || ($fromCode['html'] ?? '0') === '1' ? '1' : '0',
                'sample' => $own['sample'] !== '' ? $own['sample'] : ($fromCode['sample'] ?? ''),
                'code' => $fromCode !== null,
                'lockRequired' => ($fromCode['required'] ?? '0') === '1',
                'lockHtml' => ($fromCode['html'] ?? '0') === '1',
                'used' => in_array($name, $used, true),
            ];
        }

        return $rows;
    }

    /**
     * Значения для предпросмотра и тестового письма: пример переменной, а без него — её описание
     * (у HTML-переменной — ссылка с описанием), чтобы в письме было видно, что и где подставится.
     *
     * @param array<string, mixed> $params переменные шаблона
     * @param string[] $names какие переменные нужны (обычно — те, что есть в тексте)
     * @return array<string, string>
     */
    public static function samples(array $params, array $names): array
    {
        $params = self::normalize($params);
        $values = [];
        foreach (array_unique(array_merge($names, array_keys($params))) as $name) {
            $name = (string) $name;
            $row = $params[$name] ?? null;
            if ($row !== null && $row['sample'] !== '') {
                $values[$name] = $row['sample'];
                continue;
            }
            $label = $row !== null && $row['desc'] !== '' ? $row['desc'] : $name;
            $values[$name] = $row !== null && $row['html'] === '1'
                ? '<a href="#">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>'
                : '[' . $label . ']';
        }

        return $values;
    }

    /**
     * Имена переменных, значения которых вставляются как HTML.
     *
     * @param array<string, mixed> $params
     * @return string[]
     */
    public static function htmlNames(array $params): array
    {
        $names = [];
        foreach (self::normalize($params) as $name => $row) {
            if ($row['html'] === '1') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
