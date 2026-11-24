<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\core;

use Mitisk\Yii2Admin\models\AuditLog;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use yii\web\Response;

/**
 * POST /admin/<alias>/update/ — сохранение формы и запись изменений в аудит.
 */
final class UpdateActionTest extends TestCase
{
    public function testArrayAttributeChangeIsAudited(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['title' => 'Заметка', 'tags' => ['свадьба']]);
        $this->loginAs(self::ADMIN_ID);

        $result = $this->runAction(
            'admin/core/update',
            ['model_class' => Note::class, 'id' => $note->id],
            ['Note' => ['title' => 'Заметка', 'tags' => ['свадьба', 'банкет']]]
        );

        self::assertInstanceOf(Response::class, $result);
        self::assertSame(['свадьба', 'банкет'], Note::findOne($note->id)->tags);

        $diff = $this->auditDiff($note->id);
        self::assertArrayHasKey('tags', $diff, 'Изменение массива не попало в аудит');
        self::assertArrayNotHasKey('title', $diff, 'Неизменённое поле попало в аудит');
    }

    public function testUnchangedArrayAttributeIsNotAudited(): void
    {
        $this->registerNoteComponent();
        $note = $this->createNote(['title' => 'Было', 'tags' => ['свадьба']]);
        $this->loginAs(self::ADMIN_ID);

        $this->runAction(
            'admin/core/update',
            ['model_class' => Note::class, 'id' => $note->id],
            ['Note' => ['title' => 'Стало', 'tags' => ['свадьба']]]
        );

        $diff = $this->auditDiff($note->id);
        self::assertArrayHasKey('title', $diff);
        self::assertArrayNotHasKey('tags', $diff);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditDiff(int $noteId): array
    {
        $log = AuditLog::find()
            ->where(['model_class' => Note::class, 'model_id' => (string) $noteId, 'action' => 'update'])
            ->one();
        self::assertNotNull($log, 'Правка не попала в журнал аудита');

        return json_decode((string) $log->diff, true) ?: [];
    }
}
