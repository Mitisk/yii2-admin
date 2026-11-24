<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_support;

/**
 * Независимая от кода пакета реализация TOTP (RFC 6238, SHA-1, 6 цифр, шаг 30 с):
 * тесты входа получают из неё «код из приложения».
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function code(string $secret, ?int $time = null): string
    {
        $counter = intdiv($time ?? time(), 30);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), self::decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Код, который точно не подойдёт ни к одному из соседних шагов. */
    public static function wrongCode(string $secret): string
    {
        $valid = [];
        foreach ([-60, -30, 0, 30, 60] as $shift) {
            $valid[] = self::code($secret, time() + $shift);
        }
        for ($i = 0; $i < 1000000; $i++) {
            $candidate = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            if (!in_array($candidate, $valid, true)) {
                return $candidate;
            }
        }

        throw new \LogicException('Не удалось подобрать неверный код');
    }

    private static function decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
