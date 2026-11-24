<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * SMTP-транспорт для тестов: на каждую отправку берёт следующий исход из очереди —
 * 'ok' или исключение, которое надо бросить. Пустая очередь — 'ok'.
 */
final class ScriptedTransport implements TransportInterface
{
    public static int $calls = 0;

    /** @var array<int, string|\Throwable> */
    public static array $outcomes = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        self::$calls++;
        $outcome = array_shift(self::$outcomes) ?? 'ok';
        if ($outcome instanceof \Throwable) {
            throw $outcome;
        }

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'scripted://';
    }
}
