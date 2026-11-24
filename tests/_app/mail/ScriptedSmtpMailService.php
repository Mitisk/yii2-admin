<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\mail;

use Mitisk\Yii2Admin\components\MailService;
use yii\symfonymailer\Mailer;

/**
 * MailService, у которого SMTP-мейлер собирается на ScriptedTransport (считаем, сколько раз).
 */
final class ScriptedSmtpMailService extends MailService
{
    public static int $mailersCreated = 0;

    protected function smtpMailer(): ?Mailer
    {
        self::$mailersCreated++;

        return new Mailer(['transport' => new ScriptedTransport()]);
    }
}
