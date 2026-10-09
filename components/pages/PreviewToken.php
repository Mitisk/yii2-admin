<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\pages;

/**
 * Ссылка предпросмотра черновика без входа: `?preview=<expires>.<hmac>`.
 * Ничего не хранится в БД — подпись считается от id страницы, срока и ключа приложения.
 */
final class PreviewToken
{
    public static function create(int $pageId, string $key, int $ttl = 86400, ?int $now = null): string
    {
        $expires = ($now ?? time()) + $ttl;
        return $expires . '.' . self::sign($pageId, $expires, $key);
    }

    public static function verify(int $pageId, ?string $token, string $key, ?int $now = null): bool
    {
        if ($token === null || !preg_match('/^(\d{1,12})\.([a-f0-9]{32})$/', $token, $m)) {
            return false;
        }
        $expires = (int)$m[1];
        if ($expires < ($now ?? time())) {
            return false;
        }
        return hash_equals(self::sign($pageId, $expires, $key), $m[2]);
    }

    private static function sign(int $pageId, int $expires, string $key): string
    {
        return substr(hash_hmac('sha256', $pageId . '|' . $expires, $key), 0, 32);
    }
}
