<?php
namespace Mitisk\Yii2Admin\components;

use Yii;
use yii\helpers\Url;

final class MfaHelper
{
    /**
     * Генерация секрета (Base32-строка) для пользователя
     *
     * @param int $length
     * @return string
     * @throws \Random\RandomException
     */
    public static function generateSecret(int $length = 16): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $characters[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Функция для декодирования Base32 в бинарный формат
     *
     * @param string $base32
     * @return string
     */
    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper($base32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $decoded = '';
        $buffer = 0;
        $bitsLeft = 0;
        foreach (str_split($base32) as $char) {
            if ($char === '=') break;
            $val = strpos($alphabet, $char);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $decoded .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $decoded;
    }

    /**
     * Функция генерации 6-значного TOTP кода
     *
     * @param string $secret
     * @param int|null $timeSlice
     * @return string
     */
    private static function getTotpCode(string $secret, int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice); // 64 бит

        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary =
            ((ord($hash[$offset]) & 0x7F) << 24 ) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16 ) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8 ) |
            (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binary % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Проверка введённого кода с возможностью обработки сдвига времени
     * (-1, 0, +1 временной интервал)
     *
     * @param string $secret
     * @param string $code
     * @return bool
     */
    public static function verifyTotpCode(string $secret, string $code): bool
    {
        $currentSlice = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if (self::getTotpCode($secret, $currentSlice + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Генерация OTP URL
     * @param string $user
     * @param string $host
     * @param string $secret
     * @return string
     */
    public static function getOtpAuthUrl(string $user, string $host, string $secret): string
    {
        $issuer = urlencode($host);
        $user = urlencode($user);
        return "otpauth://totp/{$issuer}:{$user}?secret={$secret}&issuer={$issuer}";
    }
}