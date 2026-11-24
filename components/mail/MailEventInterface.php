<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\mail;

/**
 * Письмо-событие проекта с текстами по умолчанию в коде (обычно — вариант enum).
 *
 * MailService::sendEvent() берёт шаблон с кодом slug() из /admin/email-template/; если его нет —
 * создаёт из этих текстов, и дальше владелец правит письмо в админке без деплоя.
 */
interface MailEventInterface
{
    /** Код шаблона (email_templates.slug): `^[a-z0-9_-]+$`. */
    public function slug(): string;

    /** Название шаблона в админке. */
    public function label(): string;

    /** Тема по умолчанию, с переменными `{{ИМЯ}}`. */
    public function defaultSubject(): string;

    /** HTML тела по умолчанию, с переменными `{{ИМЯ}}`. */
    public function defaultBody(): string;

    /**
     * Переменные: ['ИМЯ' => ['desc' => 'Описание', 'required' => '1'|'0', 'html' => '1'|'0']].
     * required — письмо без значения не отправляется; html — значение вставляется без экранирования.
     *
     * @return array<string, array{desc: string, required?: string, html?: string}>
     */
    public function params(): array;
}
