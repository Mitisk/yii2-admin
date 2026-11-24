<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\enums;

/** Статус заметки с подписями (label()) — источник select типа enum. */
enum NotePublication: int
{
    case Draft = 0;
    case Published = 1;

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Published => 'Опубликована',
        };
    }
}
