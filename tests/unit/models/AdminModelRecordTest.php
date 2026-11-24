<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\models;

use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\tests\TestCase;

/**
 * Запись компонента (таблица admin_model): JSON формы сохраняется без порчи.
 */
final class AdminModelRecordTest extends TestCase
{
    public function testFormJsonKeepsLineBreaksAndBackslashes(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'title', 'type' => 'text', 'hint' => "Первая строка\nВторая строка"],
            ['name' => 'tags', 'type' => 'textarea', 'hint' => 'Файлы в C:\new\uploads'],
        ]);

        $data = json_decode((string) AdminModel::findOne($component->id)->data, true);

        self::assertIsArray($data, 'После сохранения data — не JSON');
        self::assertSame("Первая строка\nВторая строка", $data[0]['hint']);
        self::assertSame('Файлы в C:\new\uploads', $data[1]['hint']);
    }

    public function testEmptyArraysAreStoredAsJson(): void
    {
        $component = $this->registerNoteComponent();
        $component->list = [];
        $component->links = [];
        $component->attribute_labels = [];
        self::assertTrue($component->save());

        $stored = AdminModel::findOne($component->id);

        self::assertSame([], json_decode((string) $stored->list, true));
        self::assertSame([], $stored->links);
        self::assertSame([], $stored->attribute_labels);
    }

    public function testArrayDataIsStoredAsJson(): void
    {
        $component = $this->registerNoteComponent();
        $component->data = [['name' => 'title', 'type' => 'text']];
        self::assertTrue($component->save());

        $stored = AdminModel::findOne($component->id)->data;

        self::assertSame([['name' => 'title', 'type' => 'text']], json_decode((string) $stored, true));
    }
}
