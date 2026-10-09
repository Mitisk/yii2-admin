<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\pages;

use yii\helpers\HtmlPurifier;

/**
 * HtmlPurifier для текста страниц: `<iframe>` только с доверенных доменов
 * (и их поддоменов), ссылки могут открываться в новом окне.
 */
final class PageHtmlPurifier
{
    /**
     * @param list<string> $iframeHosts Домены без схемы: `youtube.com`, `vk.com`.
     */
    public static function process(string $html, array $iframeHosts): string
    {
        $hosts = array_values(array_filter(array_map(static fn(string $h): string => preg_quote($h, '%'), $iframeHosts)));
        $clean = HtmlPurifier::process($html, static function ($config) use ($hosts): void {
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            if ($hosts !== []) {
                $config->set('HTML.SafeIframe', true);
                $config->set('URI.SafeIframeRegexp', '%^(https?:)?//([a-z0-9-]+\.)*(' . implode('|', $hosts) . ')/%i');
            }
        });
        // Чужой адрес HtmlPurifier вырезает, а пустой <iframe> оставляет — убираем и его
        return (string)preg_replace('~<iframe(?![^>]*\ssrc=)[^>]*>\s*</iframe>~i', '', $clean);
    }

    /**
     * `youtube.com, https://vk.com/` → `['youtube.com', 'vk.com']`.
     *
     * @return list<string>
     */
    public static function hostsFromSetting(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $item) {
            $host = strtolower(trim($item));
            $host = preg_replace('~^https?://~', '', $host) ?? $host;
            $host = trim(explode('/', $host)[0]);
            if ($host !== '' && !in_array($host, $out, true)) {
                $out[] = $host;
            }
        }
        return $out;
    }
}
