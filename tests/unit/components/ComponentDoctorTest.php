<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\components;

use Mitisk\Yii2Admin\components\ComponentDoctor;
use Mitisk\Yii2Admin\components\ComponentExporter;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;

/**
 * «Проверка компонента» и экспорт настроек компонента в миграцию.
 */
final class ComponentDoctorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs(self::ADMIN_ID);
    }

    public function testHealthyComponentHasNoProblems(): void
    {
        $component = $this->registerNoteComponent();

        $report = ComponentDoctor::check($component);

        self::assertSame([], $this->titles($report, 'error'), 'Ошибки у исправного компонента');
        self::assertSame(0, ComponentDoctor::problemCount($report, 'error'));
    }

    public function testFieldOutsideRulesIsReportedAsNotSaved(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'title', 'label' => 'Заголовок', 'type' => 'text'],
            ['name' => 'updated_at', 'label' => 'Создано', 'type' => 'date'],
        ]);

        $problems = $this->texts(ComponentDoctor::check($component), 'warning');

        self::assertStringContainsString('updated_at', $problems);
        self::assertStringContainsString('rules()', $problems);
    }

    public function testReadonlyFieldOutsideRulesIsFine(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'title', 'label' => 'Заголовок', 'type' => 'text'],
            ['name' => 'updated_at', 'label' => 'Создано', 'type' => 'date', 'readonly' => true],
        ]);

        self::assertStringNotContainsString('updated_at', $this->texts(ComponentDoctor::check($component), 'warning'));
    }

    public function testUnknownFieldIsAnError(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'title', 'label' => 'Заголовок', 'type' => 'text'],
            ['name' => 'no_such_field', 'label' => 'Нет такого', 'type' => 'text'],
        ]);

        self::assertStringContainsString('no_such_field', $this->texts(ComponentDoctor::check($component), 'error'));
    }

    public function testRequiredAttributeMissingInFormIsReported(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'tags', 'label' => 'Теги', 'type' => 'textarea'],
        ]);

        $problems = $this->texts(ComponentDoctor::check($component), 'warning');

        self::assertStringContainsString('title', $problems);
        self::assertStringContainsString('обязательн', $problems);
    }

    public function testEmptyFormAndTableAreErrors(): void
    {
        $component = $this->registerNoteComponent(null, []);
        $component->updateAttributes(['list' => json_encode(['title' => ['on' => '0']])]);

        $problems = $this->texts(ComponentDoctor::check(AdminModel::findOne($component->id)), 'error');

        self::assertStringContainsString('Форма пустая', $problems);
        self::assertStringContainsString('В таблице нет колонок', $problems);
    }

    public function testSelectWithBrokenSourceIsAnError(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'title', 'label' => 'Заголовок', 'type' => 'text'],
            ['name' => 'published', 'label' => 'Статус', 'type' => 'select',
                'selectSourceType' => 'method', 'selectSourceVal' => 'getNoSuchList'],
        ]);

        self::assertStringContainsString('getNoSuchList', $this->texts(ComponentDoctor::check($component), 'error'));
    }

    public function testMissingModelClassStopsFurtherChecks(): void
    {
        $component = $this->registerNoteComponent();
        $component->updateAttributes(['model_class' => 'app\\models\\NoSuchModel']);

        $report = ComponentDoctor::check(AdminModel::findOne($component->id));

        self::assertStringContainsString('NoSuchModel', $this->texts($report, 'error'));
        self::assertSame(1, ComponentDoctor::problemCount($report, 'error'));
    }

    public function testListColumnOfUnknownAttributeIsReported(): void
    {
        $component = $this->registerNoteComponent();
        $component->updateAttributes(['list' => json_encode(['title' => ['on' => '1'], 'ghost' => ['on' => '1']])]);

        self::assertStringContainsString('ghost', $this->texts(ComponentDoctor::check(AdminModel::findOne($component->id)), 'warning'));
    }

    public function testMigrationIsValidPhpWithCurrentSettings(): void
    {
        $component = $this->registerNoteComponent();

        $code = ComponentExporter::migration(AdminModel::findOne($component->id));

        // TOKEN_PARSE бросает ParseError на синтаксически неверном коде
        self::assertNotEmpty(token_get_all($code, TOKEN_PARSE));
        self::assertStringContainsString('AdminRegistry::registerComponent([', $code);
        self::assertStringContainsString("'table_name' => 'test_note'", $code);
        self::assertStringContainsString("'model_class' => \\" . Note::class . '::class', $code);
        self::assertStringContainsString("'alias' => 'test-notes'", $code);
        self::assertStringContainsString("'label' => 'Заголовок'", $code);
        self::assertStringContainsString("AdminRegistry::unregisterComponent('test_note')", $code);
        self::assertMatchesRegularExpression('/final class m\d{6}_\d{6}_register_test_notes_admin_component extends Migration/', $code);
    }

    public function testGeneratedMigrationRecreatesTheComponent(): void
    {
        $component = AdminModel::findOne($this->registerNoteComponent(['on' => '1', 'data' => ['view' => '1']])->id);
        $before = [$component->name, $component->alias, $component->model_class, json_decode((string) $component->list, true), json_decode((string) $component->data, true)];
        $code = ComponentExporter::migration($component);
        self::assertSame(1, preg_match('/final class (m\w+) extends/', $code, $match));

        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $match[1] . '.php';
        file_put_contents($file, $code);
        try {
            AdminModel::deleteAll(['table_name' => 'test_note']);
            require $file;
            ob_start();
            (new $match[1]())->safeUp();
            ob_end_clean();
        } finally {
            @unlink($file);
        }

        $restored = AdminModel::findOne(['table_name' => 'test_note']);
        self::assertNotNull($restored, 'Миграция не создала компонент');
        self::assertSame($before, [$restored->name, $restored->alias, $restored->model_class, json_decode((string) $restored->list, true), json_decode((string) $restored->data, true)]);
    }

    public function testMigrationEscapesQuotesAndBackslashes(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'title', 'label' => "Загол'овок \\ путь", 'type' => 'text'],
        ]);

        $code = ComponentExporter::migration(AdminModel::findOne($component->id));

        self::assertNotEmpty(token_get_all($code, TOKEN_PARSE));
        self::assertStringContainsString("Загол\\'овок \\\\ путь", $code);
    }

    public function testEditorPageShowsCheckAndExportTabs(): void
    {
        $component = $this->registerNoteComponent(null, [
            ['name' => 'no_such_field', 'label' => 'Нет такого', 'type' => 'text'],
        ]);

        $html = $this->runAction('admin/components/update', ['id' => $component->id]);

        self::assertIsString($html);
        self::assertStringContainsString('id="tab-doctor"', $html);
        self::assertStringContainsString('no_such_field', $html);
        self::assertStringContainsString('id="tab-export"', $html);
        self::assertStringContainsString('AdminRegistry::registerComponent', $html);
    }

    /**
     * @param array<int, array{level: string, title: string, text: string}> $report
     * @return string[]
     */
    private function titles(array $report, string $level): array
    {
        return array_values(array_map(
            static fn (array $item): string => $item['title'],
            array_filter($report, static fn (array $item): bool => $item['level'] === $level)
        ));
    }

    /**
     * @param array<int, array{level: string, title: string, text: string}> $report
     */
    private function texts(array $report, string $level): string
    {
        $lines = [];
        foreach ($report as $item) {
            if ($item['level'] === $level) {
                $lines[] = $item['title'] . ' ' . $item['text'];
            }
        }

        return implode("\n", $lines);
    }
}
