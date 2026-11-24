<?php
namespace Mitisk\Yii2Admin\components;

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
    private static function getTotpCode(string $secret, ?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = intdiv(time(), 30);
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
     * @param string|null $secret
     * @param mixed $code
     * @return bool
     */
    public static function verifyTotpCode(?string $secret, $code): bool
    {
        return self::matchTimeSlice($secret, $code) !== null;
    }

    /**
     * Номер 30-секундного шага, которому соответствует код, или null, если код не подходит.
     * Номер нужен, чтобы не принять один и тот же код дважды.
     *
     * @param string|null $secret
     * @param mixed $code шесть цифр; пробелы допустимы («123 456»)
     * @param int|null $time момент проверки (unix), по умолчанию — сейчас
     * @return int|null
     */
    public static function matchTimeSlice(?string $secret, $code, ?int $time = null): ?int
    {
        if (!is_string($code) || (string) $secret === '') {
            return null;
        }
        $code = (string) preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $currentSlice = intdiv($time ?? time(), 30);
        $matched = null;
        // Без раннего выхода: время проверки не зависит от того, какой шаг совпал
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals(self::getTotpCode($secret, $currentSlice + $i), $code)) {
                $matched = $currentSlice + $i;
            }
        }
        return $matched;
    }

    /**
     * Секрет похож на настоящий: Base32, не короче 16 знаков.
     */
    public static function isValidSecret($secret): bool
    {
        return is_string($secret) && preg_match('/^[A-Z2-7]{16,64}$/', $secret) === 1;
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
        // rawurlencode: пробел в названии сайта должен стать %20, а не «+» (приложения показывают «+» как есть)
        $issuer = rawurlencode($host);
        $user = rawurlencode($user);
        return "otpauth://totp/{$issuer}:{$user}?secret={$secret}&issuer={$issuer}";
    }
}
