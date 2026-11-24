<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\core;

use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Флаги admin_actions компонента (кнопки «Просмотр/Изменить/Удалить» в списке)
 * запрещают и сами действия на сервере, а не только прячут кнопки.
 */
final class AdminActionsTest extends TestCase
{
    private const VIEW_OFF = ['on' => '1', 'data' => ['view' => '0', 'update' => '1', 'delete' => '1']];
    private const UPDATE_OFF = ['on' => '1', 'data' => ['view' => '1', 'update' => '0', 'delete' => '1']];
    private const DELETE_OFF = ['on' => '1', 'data' => ['view' => '1', 'update' => '1', 'delete' => '0']];
    private const ALL_OFF = ['on' => '', 'data' => ['view' => '1', 'update' => '1', 'delete' => '1']];

    public function testViewForbiddenWhenViewIsTurnedOff(): void
    {
        $this->registerNoteComponent(self::VIEW_OFF);
        $note = $this->createNote();
        $this->loginAs(self::ADMIN_ID);

        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('admin/core/view', ['model_class' => Note::class, 'id' => $note->id]);
    }

    public function testUpdateForbiddenWhenUpdateIsTurnedOff(): void
    {
        $this->registerNoteComponent(self::UPDATE_OFF);
        $note = $this->createNote(['title' => 'Было']);
        $this->loginAs(self::ADMIN_ID);

        try {
            $this->runAction(
                'admin/core/update',
                ['model_class' => Note::class, 'id' => $note->id],
                ['Note' => ['title' => 'Стало']]
            );
            self::fail('Правка прошла при выключенном update');
        } catch (ForbiddenHttpException) {
        }

        self::assertSame('Было', Note::findOne($note->id)->title);
    }

    public function testDeleteForbiddenWhenDeleteIsTurnedOff(): void
    {
        $this->registerNoteComponent(self::DELETE_OFF);
        $note = $this->createNote();
        $this->loginAs(self::ADMIN_ID);

        try {
            $this->runAction('admin/core/delete', ['model_class' => Note::class, 'id' => $note->id], []);
            self::fail('Удаление прошло при выключенном delete');
        } catch (ForbiddenHttpException) {
        }

        self::assertNotNull(Note::findOne($note->id));
    }

    public function testBatchDeleteForbiddenWhenDeleteIsTurnedOff(): void
    {
        $this->registerNoteComponent(self::DELETE_OFF);
        $note = $this->createNote();
        $this->loginAs(self::ADMIN_ID);

        try {
            $this->runAction('admin/core/batch-delete', ['model_class' => Note::class], ['ids' => [$note->id]]);
            self::fail('Массовое удаление прошло при выключенном delete');
        } catch (ForbiddenHttpException) {
        }

        self::assertNotNull(Note::findOne($note->id));
    }

    public function testSwitchedOffActionsColumnForbidsUpdate(): void
    {
        $this->registerNoteComponent(self::ALL_OFF);
        $note = $this->createNote(['title' => 'Было']);
        $this->loginAs(self::ADMIN_ID);

        $this->expectException(ForbiddenHttpException::class);
        $this->runAction(
            'admin/core/update',
            ['model_class' => Note::class, 'id' => $note->id],
            ['Note' => ['title' => 'Стало']]
        );
    }

    public function testDeleteAllowedWhenComponentHasNoActionsColumn(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote();
        $this->loginAs(self::ADMIN_ID);

        $result = $this->runAction('admin/core/delete', ['model_class' => Note::class, 'id' => $note->id], []);

        self::assertInstanceOf(Response::class, $result);
        self::assertNull(Note::findOne($note->id));
    }
}
