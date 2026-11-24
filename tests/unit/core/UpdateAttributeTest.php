<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\core;

use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\AuditLog;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * POST /admin/<alias>/update-attribute/ — переключатель «Активно/Неактивно» в списке раздела.
 */
final class UpdateAttributeTest extends TestCase
{
    private const ROUTE = 'admin/core/update-attribute';

    public function testTogglesPostedFieldOfItsComponent(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['published' => 0]);
        $this->loginAs(self::ADMIN_ID);

        $result = $this->toggle(Note::class, $note->id, 'published', '1');

        self::assertTrue($result['success']);
        self::assertSame(1, $result['count']);
        self::assertSame(1, (int) Note::findOne($note->id)->published);
    }

    public function testAcceptsModelClassAsListMarkupSendsIt(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['published' => 0]);
        $this->loginAs(self::ADMIN_ID);

        // PostedField кладёт класс в data-model через addslashes: «Mitisk\\Yii2Admin\\...»
        $result = $this->toggle(addslashes(Note::class), $note->id, 'published', '1');

        self::assertSame(1, $result['count']);
        self::assertSame(1, (int) Note::findOne($note->id)->published);
    }

    public function testManagerCannotChangeRecordOfAnotherModel(): void
    {
        $this->registerNoteComponent();
        $manager = $this->createUser('note-manager', [Note::class . '\update']);
        $this->loginAs($manager->id);

        $result = $this->toggle(AdminUser::class, self::ADMIN_ID, 'status', '0');

        self::assertSame(0, $result['count']);
        self::assertSame(AdminUser::STATUS_ACTIVE, (int) AdminUser::findOne(self::ADMIN_ID)->status);
    }

    public function testRejectsAttributeThatIsNotAPostedField(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['title' => 'Было']);
        $this->loginAs(self::ADMIN_ID);

        $result = $this->toggle(Note::class, $note->id, 'title', 'Стало');

        self::assertSame(0, $result['count']);
        self::assertSame('Было', Note::findOne($note->id)->title);
    }

    public function testRejectsReadonlyPostedField(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['archived' => 0]);
        $this->loginAs(self::ADMIN_ID);

        $result = $this->toggle(Note::class, $note->id, 'archived', '1');

        self::assertSame(0, $result['count']);
        self::assertSame(0, (int) Note::findOne($note->id)->archived);
    }

    public function testValidatesValueByModelRules(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['published' => 0]);
        $this->loginAs(self::ADMIN_ID);

        $result = $this->toggle(Note::class, $note->id, 'published', '5');

        self::assertFalse($result['success']);
        self::assertSame(0, (int) Note::findOne($note->id)->published);
    }

    public function testWritesChangeToAuditLog(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['published' => 0]);
        $this->loginAs(self::ADMIN_ID);

        $this->toggle(Note::class, $note->id, 'published', '1');

        $log = AuditLog::find()
            ->where(['model_class' => Note::class, 'model_id' => (string) $note->id, 'action' => 'update'])
            ->one();
        self::assertNotNull($log, 'Переключение не попало в журнал аудита');
        self::assertStringContainsString('published', (string) $log->diff);
    }

    public function testForbiddenWhenUpdateIsTurnedOffForComponent(): void
    {
        $this->registerNoteComponent(['on' => '1', 'data' => ['view' => '1', 'update' => '0', 'delete' => '1']]);
        $note = $this->createNote(['published' => 0]);
        $this->loginAs(self::ADMIN_ID);

        $this->expectException(ForbiddenHttpException::class);
        $this->toggle(Note::class, $note->id, 'published', '1');
    }

    public function testModelClassMustBeARegisteredComponent(): void
    {
        $this->loginAs(self::ADMIN_ID);

        $this->expectException(NotFoundHttpException::class);
        $this->runAction(self::ROUTE, ['model_class' => AdminUser::class], [
            'items' => [['id' => self::ADMIN_ID, 'model' => AdminUser::class, 'attribute' => 'status', 'value' => '0']],
        ]);
    }

    /**
     * @return array{success: bool, count: int, errors: array<int, string>}
     */
    private function toggle(string $modelClass, int $id, string $attribute, string $value): array
    {
        return $this->runAction(self::ROUTE, ['model_class' => Note::class], [
            'items' => [['id' => $id, 'model' => $modelClass, 'attribute' => $attribute, 'value' => $value]],
        ]);
    }
}
