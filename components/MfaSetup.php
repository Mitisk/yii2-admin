<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Yii;

/**
 * Подключение приложения-аутентификатора в карточке пользователя.
 *
 * Новый секрет рождается на сервере и до подтверждения кодом живёт только в сессии того, кто
 * открыл форму: из запроса секрет не принимается, а действующий секрет пользователя в страницу
 * не выводится никогда. В базу секрет попадает после того, как код из приложения подошёл, —
 * так нельзя включить вход по коду, не настроив приложение, и остаться без доступа.
 */
final class MfaSetup
{
    /** Сколько секунд показанный QR-код остаётся годным для подтверждения */
    public const TTL = 3600;

    private const SESSION_KEY = '__admin_mfa_setup';
    /** Сколько незавершённых подключений помнит одна сессия */
    private const MAX_PENDING = 5;

    /**
     * Секрет, который сейчас подключают пользователю (0 — пользователь ещё не создан).
     * Пока подключение не завершено и не истекло, возвращается один и тот же секрет:
     * после ошибки в форме QR-код не меняется.
     */
    public static function secret(int $userId): string
    {
        $pending = self::pending();
        if (isset($pending[$userId])) {
            return $pending[$userId]['secret'];
        }

        $pending[$userId] = ['secret' => MfaHelper::generateSecret(), 'at' => time()];
        if (count($pending) > self::MAX_PENDING) {
            $pending = array_slice($pending, -self::MAX_PENDING, null, true);
        }
        self::store($pending);

        return $pending[$userId]['secret'];
    }

    /**
     * Код из приложения подходит к секрету, показанному в форме этого пользователя.
     */
    public static function confirm(int $userId, mixed $code): ?string
    {
        $pending = self::pending();
        if (!isset($pending[$userId])) {
            return null;
        }
        $secret = $pending[$userId]['secret'];

        return MfaHelper::verifyTotpCode($secret, $code) ? $secret : null;
    }

    /**
     * Подключение завершено или отменено: следующий раз будет новый секрет.
     */
    public static function forget(int $userId): void
    {
        $pending = self::pending();
        if (isset($pending[$userId])) {
            unset($pending[$userId]);
            self::store($pending);
        }
    }

    /**
     * @return array<int, array{secret: string, at: int}>
     */
    private static function pending(): array
    {
        if (!self::hasSession()) {
            return [];
        }
        $raw = Yii::$app->session->get(self::SESSION_KEY);
        $pending = [];
        foreach (is_array($raw) ? $raw : [] as $userId => $row) {
            $fresh = is_array($row)
                && MfaHelper::isValidSecret($row['secret'] ?? null)
                && time() - (int) ($row['at'] ?? 0) <= self::TTL;
            if ($fresh) {
                $pending[(int) $userId] = ['secret' => $row['secret'], 'at' => (int) $row['at']];
            }
        }

        return $pending;
    }

    /**
     * @param array<int, array{secret: string, at: int}> $pending
     */
    private static function store(array $pending): void
    {
        if (!self::hasSession()) {
            return;
        }
        if ($pending === []) {
            Yii::$app->session->remove(self::SESSION_KEY);
        } else {
            Yii::$app->session->set(self::SESSION_KEY, $pending);
        }
    }

    private static function hasSession(): bool
    {
        return Yii::$app !== null && Yii::$app->has('session');
    }
}
