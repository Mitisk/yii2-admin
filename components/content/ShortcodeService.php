<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\content;

use Yii;

/**
 * Шорткоды вида `[name attr="value" other="x"]` в HTML страниц.
 *
 * Обработчик: `fn(array $attrs): string`. Неизвестные шорткоды остаются как есть,
 * содержимое `<pre>` и `<code>` не трогается, исключение обработчика — пустая строка
 * и warning в лог (страница сайта не должна падать из-за одного шорткода).
 */
class ShortcodeService
{
    private const TAG = '/\[([a-z][a-z0-9_-]*)((?:\s+[a-z_][a-z0-9_-]*="[^"]*")*)\s*\/?\]/i';
    private const PROTECTED = '/(<pre\b[\s\S]*?<\/pre>|<code\b[\s\S]*?<\/code>)/i';

    /** @var array<string, callable> */
    private array $handlers = [];

    public function register(string $name, callable $handler): static
    {
        $this->handlers[strtolower($name)] = $handler;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->handlers[strtolower($name)]);
    }

    public function render(string $html): string
    {
        if ($this->handlers === [] || !str_contains($html, '[')) {
            return $html;
        }
        $parts = preg_split(self::PROTECTED, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = $this->replace($part);
            }
        }
        return implode('', $parts);
    }

    private function replace(string $html): string
    {
        return (string)preg_replace_callback(self::TAG, function (array $m): string {
            $name = strtolower($m[1]);
            if (!isset($this->handlers[$name])) {
                return $m[0];
            }
            preg_match_all('/([a-z_][a-z0-9_-]*)="([^"]*)"/i', $m[2], $pairs, PREG_SET_ORDER);
            $attrs = [];
            foreach ($pairs as $p) {
                $attrs[strtolower($p[1])] = html_entity_decode($p[2], ENT_QUOTES | ENT_HTML5);
            }
            try {
                return (string)call_user_func($this->handlers[$name], $attrs);
            } catch (\Throwable $e) {
                Yii::warning('Shortcode [' . $name . ']: ' . $e->getMessage(), __METHOD__);
                return '';
            }
        }, $html);
    }
}
