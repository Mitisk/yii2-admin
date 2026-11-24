<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\fields;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Mitisk\Yii2Admin\fields\Field;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Поле типа user: фильтр колонки в списке и форма.
 */
final class UserFieldTest extends TestCase
{
    private const FIELDS = [
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'author_id', 'type' => 'user'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->set('user', Yii::$app->get('adminUser'));
        $this->loginAs(self::ADMIN_ID);
    }

    public function testFilterByNumberFindsRecordsOfThatUserId(): void
    {
        $this->registerNoteComponent(null, self::FIELDS);
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $aliceNote = $this->createNote(['title' => 'Заметка Алисы', 'author_id' => $alice->id]);
        $this->createNote(['title' => 'Заметка Боба', 'author_id' => $bob->id]);

        self::assertSame([$aliceNote->id], $this->filterIds((string) $alice->id));
    }

    public function testFilterByTextStillSearchesLoginNameAndEmail(): void
    {
        $this->registerNoteComponent(null, self::FIELDS);
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->createNote(['title' => 'Заметка Алисы', 'author_id' => $alice->id]);
        $bobNote = $this->createNote(['title' => 'Заметка Боба', 'author_id' => $bob->id]);

        self::assertSame([$bobNote->id], $this->filterIds('bob'));
    }

    public function testReadonlyUserFieldCannotBeChangedInForm(): void
    {
        $this->registerNoteComponent(null, [
            ['name' => 'author_id', 'type' => 'user', 'readonly' => true],
        ]);
        $note = $this->createNote(['author_id' => self::ADMIN_ID]);
        $model = new AdminModel($note);
        $input = json_decode($model->component->data, true)[0];

        $html = (new Field(['input' => $input, 'model' => $model]))->getFormInput();

        self::assertStringContainsString('user-field-selected', $html, 'Выбранный пользователь не показан');
        self::assertStringNotContainsString('user-field-input', $html, 'У readonly-поля есть поиск');
        self::assertStringNotContainsString('user-field-clear', $html, 'У readonly-поля есть кнопка «Убрать»');
    }

    /**
     * @return int[]
     */
    private function filterIds(string $value): array
    {
        $filter = new Note();
        $filter->author_id = $value;
        $provider = (new AdminModel(new Note()))->search(null, $filter);
        $provider->pagination = false;

        return array_map(static fn (Note $note): int => $note->id, $provider->getModels());
    }
}
