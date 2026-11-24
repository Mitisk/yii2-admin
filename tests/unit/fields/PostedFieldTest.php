<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\fields;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Mitisk\Yii2Admin\fields\Field;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Колонка поля posted в списке: переключатель только там, где сервер примет update-attribute.
 */
final class PostedFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->set('user', Yii::$app->get('adminUser'));
    }

    public function testEditablePostedFieldRendersToggle(): void
    {
        $this->registerNoteComponent();
        $this->loginAs(self::ADMIN_ID);

        self::assertStringContainsString('js-toggle-published', $this->cell('published'));
    }

    public function testReadonlyPostedFieldRendersStatusWithoutToggle(): void
    {
        $this->registerNoteComponent();
        $this->loginAs(self::ADMIN_ID);

        self::assertStringNotContainsString('js-toggle-published', $this->cell('archived'));
    }

    public function testNoToggleWhenComponentForbidsUpdate(): void
    {
        $this->registerNoteComponent(['on' => '1', 'data' => ['view' => '1', 'update' => '0', 'delete' => '1']]);
        $this->loginAs(self::ADMIN_ID);

        self::assertStringNotContainsString('js-toggle-published', $this->cell('published'));
    }

    private function cell(string $attribute): string
    {
        $note = $this->createNote(['published' => 1, 'archived' => 1]);
        $adminModel = new AdminModel($note);
        $input = null;
        foreach (json_decode($adminModel->component->data, true) as $item) {
            if (($item['name'] ?? null) === $attribute) {
                $input = $item;
            }
        }
        self::assertNotNull($input);

        $column = (new Field(['input' => $input, 'model' => $adminModel]))->getListData($attribute);

        return (string) ($column['value'])($note);
    }
}
