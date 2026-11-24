<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\components;

use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Проверка alias компонента на занятость.
 */
final class ReservedAliasTest extends TestCase
{
    public function testAliasIsFreeAgainRightAfterComponentRemoval(): void
    {
        $this->registerNoteComponent();
        // Кэш мог заполниться и до регистрации «Заметок» — начинаем с чистого, как новый запрос
        Yii::$app->cache->flush();

        // Любая проверка alias, пока «Заметки» на месте (например, регистрация другого раздела)
        $other = new AdminModel(['table_name' => 'other_table', 'alias' => 'other-section']);
        self::assertTrue($other->validate(['alias']));

        // migrate/down убрал раздел — и тот же alias регистрируется снова (migrate/up)
        AdminModel::deleteAll(['table_name' => 'test_note']);
        $again = new AdminModel([
            'table_name' => 'test_note',
            'alias' => 'test-notes',
            'model_class' => Note::class,
        ]);

        self::assertTrue($again->validate(['alias']), implode(', ', $again->getErrorSummary(true)));
    }

    public function testTechnicalAliasIsReserved(): void
    {
        $component = new AdminModel(['table_name' => 'other_table', 'alias' => 'content-block']);

        self::assertFalse($component->validate(['alias']));
    }

    public function testAliasOfExistingComponentIsTaken(): void
    {
        $this->registerNoteComponent();
        $component = new AdminModel(['table_name' => 'other_table', 'alias' => 'test-notes']);

        self::assertFalse($component->validate(['alias']));
    }
}
