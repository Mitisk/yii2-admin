<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\fields;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Mitisk\Yii2Admin\fields\Field;
use Mitisk\Yii2Admin\tests\_app\enums\NoteColor;
use Mitisk\Yii2Admin\tests\_app\enums\NotePublication;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Поле select с источником «PHP enum» (selectSourceType: enum, selectSourceVal: FQCN BackedEnum).
 */
final class SelectEnumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->set('user', Yii::$app->get('adminUser'));
        $this->loginAs(self::ADMIN_ID);
    }

    public function testFormListsEnumCasesWithLabels(): void
    {
        $html = $this->field(NotePublication::class)->getFormInput();

        self::assertMatchesRegularExpression('/<option value="0"[^>]*>Черновик<\/option>/u', $html);
        self::assertMatchesRegularExpression('/<option value="1"[^>]*>Опубликована<\/option>/u', $html);
    }

    public function testEnumWithoutLabelUsesCaseNames(): void
    {
        $html = $this->field(NoteColor::class)->getFormInput();

        self::assertMatchesRegularExpression('/<option value="red"[^>]*>Red<\/option>/u', $html);
    }

    public function testListCellShowsLabelOfStoredValue(): void
    {
        $column = $this->field(NotePublication::class, ['published' => 1])->getListData('published');

        self::assertStringContainsString('Опубликована', (string) ($column['value'])($this->note));
    }

    public function testUnknownEnumClassGivesEmptyListInsteadOfError(): void
    {
        $html = $this->field('app\\enums\\NoSuchEnum')->getFormInput();

        self::assertStringContainsString('name="Note[published]"', $html);
        self::assertStringNotContainsString('Опубликована', $html);
    }

    private \Mitisk\Yii2Admin\tests\_app\models\Note $note;

    /**
     * @param array<string, mixed> $noteAttributes
     */
    private function field(string $enumClass, array $noteAttributes = []): Field
    {
        $input = [
            'name' => 'published',
            'label' => 'Статус',
            'type' => 'select',
            'selectSourceType' => 'enum',
            'selectSourceVal' => $enumClass,
        ];
        $this->registerNoteComponent(null, [$input]);
        $this->note = $this->createNote($noteAttributes);

        return new Field(['input' => $input, 'model' => new AdminModel($this->note)]);
    }
}
