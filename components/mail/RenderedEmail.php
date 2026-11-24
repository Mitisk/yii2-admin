<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\mail;

/**
 * Готовое письмо: тема одной строкой и HTML тела (уже в макете).
 */
final class RenderedEmail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
    ) {
    }
}
