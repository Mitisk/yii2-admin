<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\log;

/**
 * Прячет секреты в тексте системного лога перед показом в админке: ключи входа, токены, пароли,
 * идентификаторы сессий. Остаются первые четыре символа — по ним запись можно узнать, но не использовать.
 */
final class LogMask
{
    private const KEEP = 4;
    private const DOTS = '••••';

    /** «token=…», «password: …», «auth key … : …» — значение после имени. */
    private const NAMED = '/\b((?:auth[ _-]?key|access[ _-]?token|refresh[ _-]?token|token|secret|password|passwd|api[ _-]?key|authorization|cookie|sessid|session[ _-]?id)\b[^\r\n=:]{0,60}?[=:]\s*["\']?)([^\s"\',;&)\]]{6,})/iu';

    /** Кандидат в случайную строку: от 24 знаков, не часть пути или имени класса (см. looksRandom). */
    private const RANDOM = '/(?<![A-Za-z0-9_\-\\\\\/.])[A-Za-z0-9_\-]{24,}(?![A-Za-z0-9_\-\\\\\/.])/';

    public static function mask(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $text = (string) preg_replace_callback(
            self::NAMED,
            static fn (array $m): string => $m[1] . self::short($m[2]),
            $text
        );

        return (string) preg_replace_callback(
            self::RANDOM,
            static fn (array $m): string => self::looksRandom($m[0]) ? self::short($m[0]) : $m[0],
            $text
        );
    }

    /**
     * Ключ или идентификатор сессии, а не имя: есть цифра, и либо смешаны регистры
     * (ключ входа Yii), либо нет разделителей (hex, id сессии). Имя миграции
     * `m261125_120000_create_admin_cron_tables` под это не попадает.
     */
    private static function looksRandom(string $token): bool
    {
        if (preg_match('/\d/', $token) !== 1 || preg_match('/[A-Za-z]/', $token) !== 1) {
            return false;
        }
        $mixedCase = preg_match('/[a-z]/', $token) === 1 && preg_match('/[A-Z]/', $token) === 1;

        return $mixedCase || !preg_match('/[_\-]/', $token);
    }

    private static function short(string $secret): string
    {
        if (str_ends_with($secret, self::DOTS)) {
            return $secret;
        }

        return mb_substr($secret, 0, self::KEEP) . self::DOTS;
    }
}
