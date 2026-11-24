<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\mail;

use yii\helpers\Html;

/**
 * Подстановка переменных `{{ИМЯ}}` в тему и тело письма и оборачивание тела в макет.
 *
 * - В теле значения экранируются (переносы строк — `<br>`), кроме переменных из $htmlParams.
 * - В теме — как есть, одной строкой (переводы строк и табы — пробелы), не длиннее 255 символов.
 * - Все `{{ИМЯ}}` в тексте заменяются: без значения — пустой строкой, чтобы метка не попала к человеку.
 * - Подстановка в один проход: значение с `{{ДРУГОЕ}}` внутри не раскрывается повторно.
 * - `%7B%7BИМЯ%7D%7D` (так визуальный редактор кодирует метку в href) понимается как `{{ИМЯ}}`.
 *
 * Чистый класс без обращений к БД и приложению.
 */
final class TemplateRenderer
{
    private const SUBJECT_MAX = 255;

    /**
     * @param array<string, string|int|float|null> $params значения переменных
     * @param string[] $htmlParams имена переменных, значения которых вставляются в тело без экранирования
     * @param string|null $layout макет с маркером `{{content}}`; без маркера игнорируется
     */
    public function render(string $subject, string $body, array $params, array $htmlParams, ?string $layout): RenderedEmail
    {
        if ($layout !== null && str_contains($layout, '{{content}}')) {
            $body = str_replace('{{content}}', $body, $layout);
        }
        $body = (string)preg_replace('/%7B%7B(\w+)%7D%7D/i', '{{$1}}', $body);

        $names = [];
        foreach ([$subject, $body] as $text) {
            if (preg_match_all('/\{\{(\w+)\}\}/', $text, $m)) {
                foreach ($m[1] as $name) {
                    $names[$name] = true;
                }
            }
        }
        unset($names['content']);

        $raw = [];
        $escaped = [];
        foreach (array_keys($names) as $name) {
            $value = (string)($params[$name] ?? '');
            $raw['{{' . $name . '}}'] = $value;
            $escaped['{{' . $name . '}}'] = in_array($name, $htmlParams, true)
                ? $value
                : nl2br(Html::encode($value));
        }

        $subject = $raw ? strtr($subject, $raw) : $subject;
        $html = $escaped ? strtr($body, $escaped) : $body;
        $subject = trim((string)preg_replace('/ {2,}/', ' ', str_replace(["\r", "\n", "\t"], ' ', $subject)));

        return new RenderedEmail(mb_substr($subject, 0, self::SUBJECT_MAX), $html);
    }
}
