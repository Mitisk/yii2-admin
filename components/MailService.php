<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\components\mail\TemplateRenderer;
use Mitisk\Yii2Admin\models\EmailTemplate;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;
use Yii;
use yii\base\Component;
use yii\di\Instance;
use yii\mail\MailerInterface;
use yii\symfonymailer\Mailer;

/**
 * Отправка писем по шаблонам из админки (`/admin/email-template/`).
 *
 * - Никогда не бросает исключений: при сбое возвращает false и пишет в лог `mail` только slug шаблона
 *   и класс ошибки — без адресов и значений переменных.
 * - Значения переменных в теле экранируются; переменная с `'html' => '1'` в params шаблона
 *   вставляется как есть. Неизвестные `{{ИМЯ}}` заменяются пустой строкой (TemplateRenderer).
 * - SMTP из настроек админки: одно соединение на процесс (запрос, крон), таймаут {@see SMTP_TIMEOUT}.
 *   Сервер недоступен — остальные письма процесса сразу возвращают false, без новых попыток соединиться.
 *   Сервер отклонил письмо (например, нет такого адреса) — следующее идёт по новому соединению.
 * - Свойство $mailer отправляет через мейлер приложения вместо SMTP (разработка, тесты).
 */
class MailService extends Component
{
    public const SMTP_TIMEOUT = 10.0;

    public const LOG_CATEGORY = 'mail';

    private const SMTP_SECTION = 'Mitisk\Yii2Admin\models\MailTemplate';

    /**
     * Куда отправлять вместо SMTP из настроек: id компонента приложения ('mailer'), конфиг или объект
     * MailerInterface. null — SMTP из настроек админки. Например, вне прода:
     * `Yii::$container->set(MailService::class, ['mailer' => 'mailer'])` — письма уйдут в мейлер приложения.
     *
     * @var string|array<string, mixed>|MailerInterface|null
     */
    public $mailer;

    /** Общий SMTP-мейлер процесса: Symfony держит соединение открытым между письмами. */
    private static ?Mailer $sharedSmtp = null;

    /** Сбой соединения с SMTP: до конца процесса письма не шлём и не соединяемся снова. */
    private static bool $smtpDown = false;

    /**
     * Забыть общее SMTP-соединение и отметку «сервер недоступен» (долгоживущие воркеры, тесты).
     */
    public static function resetConnection(): void
    {
        self::$sharedSmtp = null;
        self::$smtpDown = false;
    }

    /**
     * Отправка письма по шаблону.
     *
     * @param string $slug Уникальный код шаблона
     * @param string $to Email получателя
     * @param array<string, string|int|float|null> $params Значения переменных ['NAME' => 'Иван']
     * @return bool Успешность отправки
     */
    public function send(string $slug, string $to, array $params = []): bool
    {
        try {
            $template = EmailTemplate::findOne(['slug' => $slug, 'active' => 1]);
            if ($template === null) {
                Yii::warning("MailService: шаблон {$slug} не найден или выключен", self::LOG_CATEGORY);
                return false;
            }

            $htmlParams = [];
            foreach (is_array($template->params) ? $template->params : [] as $key => $config) {
                $config = is_array($config) ? $config : [];
                if (!empty($config['required']) && (string)($params[$key] ?? '') === '') {
                    Yii::error("MailService: {$slug} не отправлено: нет обязательной переменной {$key}", self::LOG_CATEGORY);
                    return false;
                }
                if (!empty($config['html'])) {
                    $htmlParams[] = (string)$key;
                }
            }

            return $this->deliver($slug, $to, (string)$template->subject, (string)$template->body, $params, $htmlParams);
        } catch (Throwable $e) {
            Yii::error("MailService: {$slug} не отправлено: " . $e::class, self::LOG_CATEGORY);
            return false;
        }
    }

    /**
     * Рендер (переменные, макет) и отправка. Исключения транспорта уходят вызывающему.
     *
     * @param array<string, string|int|float|null> $params
     * @param string[] $htmlParams
     * @throws Throwable
     */
    protected function deliver(string $slug, string $to, string $subject, string $body, array $params, array $htmlParams): bool
    {
        $layout = Yii::$app->settings->get('HIDDEN', 'mail_layout');
        $email = (new TemplateRenderer())->render(
            $subject,
            $body,
            $params,
            $htmlParams,
            is_string($layout) && $layout !== '' ? $layout : null,
        );

        if ($this->mailer !== null) {
            /** @var MailerInterface $mailer */
            $mailer = is_string($this->mailer)
                ? Yii::$app->get($this->mailer)
                : Instance::ensure($this->mailer, MailerInterface::class);
            return $mailer->compose()
                ->setFrom([$this->fromEmail() => $this->fromName()])
                ->setTo($to)
                ->setSubject($email->subject)
                ->setHtmlBody($email->html)
                ->send();
        }

        if (self::$smtpDown) {
            Yii::warning("MailService: {$slug} не отправлено: SMTP недоступен", self::LOG_CATEGORY);
            return false;
        }
        $mailer = self::$sharedSmtp ??= $this->smtpMailer();
        if ($mailer === null) {
            Yii::warning("MailService: {$slug} не отправлено: SMTP не настроен", self::LOG_CATEGORY);
            return false;
        }

        $message = $mailer->compose()
            ->setFrom([$this->fromEmail() => $this->fromName()])
            ->setTo($to)
            ->setSubject($email->subject)
            ->setHtmlBody($email->html);

        try {
            // Напрямую через Symfony: мейлер Yii сам ловит исключения и пишет в свой лог их текст,
            // а в нём адрес получателя из ответа сервера
            $mailer->getSymfonyMailer()->send($message->getSymfonyEmail());
        } catch (UnexpectedResponseException $e) {
            // Сервер ответил отказом по этому письму — связь есть, но следующее письмо — по чистому соединению
            self::$sharedSmtp = null;
            throw $e;
        } catch (TransportExceptionInterface $e) {
            // Нет связи, таймаут, TLS, авторизация — сервер недоступен до конца процесса
            self::$sharedSmtp = null;
            self::$smtpDown = true;
            throw $e;
        }

        return true;
    }

    /**
     * Новый SMTP-мейлер из настроек админки (своё соединение); null — хост не задан.
     * Вызывается при первом письме процесса и после отказа сервера.
     */
    protected function smtpMailer(): ?Mailer
    {
        $host = trim((string)$this->getSetting('mailserver_host'));
        if ($host === '') {
            return null;
        }
        $port = (int)($this->getSetting('mailserver_port') ?: 587);

        $mailer = new Mailer([
            'useFileTransport' => false,
            'transport' => [
                'scheme' => $port === 465 ? 'smtps' : 'smtp',
                'host' => $host,
                'username' => (string)$this->getSetting('mailserver_login'),
                'password' => (string)$this->getSetting('mailserver_password'),
                'port' => $port,
            ],
        ]);
        $transport = $mailer->getTransport();
        if ($transport instanceof EsmtpTransport) {
            $transport->getStream()->setTimeout(self::SMTP_TIMEOUT);
        }

        return $mailer;
    }

    /** Адрес отправителя: логин SMTP, иначе email администратора из основных настроек. */
    protected function fromEmail(): string
    {
        $login = trim((string)$this->getSetting('mailserver_login'));
        if ($login !== '') {
            return $login;
        }
        return trim((string)Yii::$app->settings->get('GENERAL', 'admin_email', '')) ?: 'noreply@localhost';
    }

    protected function fromName(): string
    {
        return (string)($this->getSetting('mailserver_from_name') ?: Yii::$app->name);
    }

    private function getSetting(string $key): mixed
    {
        return Yii::$app->settings->get(self::SMTP_SECTION, $key);
    }
}
