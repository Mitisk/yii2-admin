<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\enums;

/** Enum без label(): подписи — имена вариантов. */
enum NoteColor: string
{
    case Red = 'red';
    case Green = 'green';
}
