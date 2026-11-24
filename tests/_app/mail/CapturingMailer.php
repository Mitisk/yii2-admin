<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\mail;

use yii\mail\BaseMailer;
use yii\mail\MessageInterface;
use yii\symfonymailer\Message;

/**
 * Мейлер приложения для тестов: письма складываются в память.
 */
final class CapturingMailer extends BaseMailer
{
    /** @var MessageInterface[] */
    public static array $sent = [];

    public $messageClass = Message::class;

    public $useFileTransport = false;

    protected function sendMessage($message): bool
    {
        self::$sent[] = $message;

        return true;
    }
}
