<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\components\WafBridge;
use yii\helpers\Url;

/**
 * Карточка дашборда «Заблокировано сканеров за 7 дней». Без пакета mitisk/yii2-lightweight-waf
 * ничего не выводит.
 */
final class WafStatWidget extends StatWidget
{
    public function run(): string
    {
        return WafBridge::isSupported() ? parent::run() : '';
    }

    protected function stat(): array
    {
        $stats = WafBridge::control()->stats(7);
        $config = WafBridge::config();
        if (!$config->enabled) {
            $note = 'защита выключена';
        } elseif ($config->observeOnly) {
            $note = 'режим наблюдения';
        } else {
            $note = 'сейчас под блокировкой: ' . number_format($stats['active'], 0, '.', ' ');
        }

        return [
            'title' => 'Сканеров заблокировано за 7 дней',
            'icon' => 'icon-shield',
            'value' => $stats['bans'],
            'note' => $note,
            'url' => Url::to(['/admin/waf/index']),
        ];
    }
}
