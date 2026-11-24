<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app;

use yii\web\Session;

/**
 * Сессия в памяти процесса: в CLI-тестах session_start() не нужен (flash-сообщения пишутся в $_SESSION).
 */
final class ArraySession extends Session
{
    private bool $active = false;

    public function open(): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        $this->active = true;
    }

    public function close(): void
    {
        $this->active = false;
    }

    public function destroy(): void
    {
        $_SESSION = [];
    }

    public function getIsActive(): bool
    {
        return $this->active;
    }

    public function getId(): string
    {
        return 'yii2admin-test';
    }

    public function regenerateID($deleteOldSession = false): void
    {
    }
}
