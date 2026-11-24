<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\mail;

use Mitisk\Yii2Admin\components\mail\MailEventInterface;

/**
 * События писем тестового приложения: тексты по умолчанию живут в коде.
 */
enum TestMailEvent: string implements MailEventInterface
{
    case OrderPaid = 'test_order_paid';

    public function slug(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return 'Заказ оплачен';
    }

    public function defaultSubject(): string
    {
        return 'Заказ №{{ID}} оплачен';
    }

    public function defaultBody(): string
    {
        return '<p>{{NAME}}, спасибо! {{BUTTON}}</p>';
    }

    public function params(): array
    {
        return [
            'ID' => ['desc' => 'Номер заказа', 'required' => '1'],
            'NAME' => ['desc' => 'Имя', 'required' => '0'],
            'BUTTON' => ['desc' => 'Кнопка «Открыть заказ»', 'required' => '0', 'html' => '1'],
        ];
    }
}
