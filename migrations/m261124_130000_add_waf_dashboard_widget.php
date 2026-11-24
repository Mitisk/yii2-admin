<?php

declare(strict_types=1);

use Mitisk\Yii2Admin\components\AdminRegistry;
use Mitisk\Yii2Admin\widgets\WafStatWidget;
use yii\db\Migration;

/**
 * Карточка дашборда «Сканеров заблокировано за 7 дней». Показывается только в проектах,
 * где установлен mitisk/yii2-lightweight-waf: без пакета виджет ничего не выводит.
 */
class m261124_130000_add_waf_dashboard_widget extends Migration
{
    public function safeUp(): void
    {
        AdminRegistry::registerDashboardWidget('WafStatWidget', WafStatWidget::class, 3);
    }

    public function safeDown(): void
    {
        AdminRegistry::unregisterDashboardWidget('WafStatWidget');
    }
}
