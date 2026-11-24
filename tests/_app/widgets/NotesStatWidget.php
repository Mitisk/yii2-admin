<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\widgets;

use Mitisk\Yii2Admin\widgets\StatWidget;

/**
 * Карточка дашборда для тестов StatWidget: данные задаются снаружи.
 */
final class NotesStatWidget extends StatWidget
{
    /** @var array<string, mixed> */
    public static array $data = [];

    /** @var string|string[]|null */
    public static $requiredPermission = 'admin';

    public function init(): void
    {
        parent::init();
        $this->permission = self::$requiredPermission;
    }

    protected function stat(): array
    {
        return self::$data;
    }
}
