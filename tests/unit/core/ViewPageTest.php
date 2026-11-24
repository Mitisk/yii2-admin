<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\core;

use Mitisk\Yii2Admin\core\controllers\AdminController;
use Mitisk\Yii2Admin\core\models\AdminModel;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Кнопки на странице просмотра записи (views/core/view.php).
 */
final class ViewPageTest extends TestCase
{
    public function testEditButtonFollowsUpdateRightNotCreateRight(): void
    {
        $component = $this->registerNoteComponent();
        $component->can_create = 0;
        self::assertTrue($component->save());

        self::assertStringContainsString('Редактировать', $this->renderViewPage());
    }

    public function testNoEditButtonWhenUpdateIsTurnedOff(): void
    {
        $this->registerNoteComponent(['on' => '1', 'data' => ['view' => '1', 'update' => '0', 'delete' => '1']]);

        self::assertStringNotContainsString('Редактировать', $this->renderViewPage());
    }

    private function renderViewPage(): string
    {
        $this->loginAs(self::ADMIN_ID);
        Yii::$app->set('user', Yii::$app->get('adminUser'));
        Yii::$app->controller = new AdminController('core', Yii::$app->getModule('admin'));

        $model = new AdminModel($this->createNote(['title' => 'Заметка']));

        return Yii::$app->view->render('@Mitisk/Yii2Admin/views/core/view.php', ['model' => $model]);
    }
}
