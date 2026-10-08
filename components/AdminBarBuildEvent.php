<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use yii\base\Event;

/**
 * Событие сборки состояния панели администратора ({@see AdminBarState::EVENT_BUILD}).
 *
 * Обработчик меняет `$event->state` напрямую: добавляет панели, действия, бейджи.
 */
class AdminBarBuildEvent extends Event
{
    public AdminBarState $state;
}
