<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\assets;

use Mitisk\Yii2Admin\components\AssetPublisher;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\helpers\FileHelper;
use yii\web\AssetManager;

/**
 * Первая страница админки после обновления сама публикует стили целиком (с отметкой «готово»),
 * AJAX-запросы этого не делают.
 */
final class AdminAssetsOnFirstRequestTest extends TestCase
{
    private string $webDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'admin-web-' . bin2hex(random_bytes(5));
        mkdir($this->webDir, 0777, true);
        Yii::$app->set('assetManager', new AssetManager([
            'basePath' => $this->webDir,
            'baseUrl' => '/assets',
            'linkAssets' => false,
            'forceCopy' => false,
        ]));
    }

    protected function tearDown(): void
    {
        FileHelper::removeDirectory($this->webDir);
        parent::tearDown();
    }

    public function testLoginPagePublishesAdminAssetsCompletely(): void
    {
        $html = $this->runAction('admin/default/login');

        $path = Yii::$app->assetManager->getPublishedPath((string) Yii::getAlias('@Mitisk/Yii2Admin/assets'));
        self::assertIsString($html);
        self::assertFileExists($path . '/' . AssetPublisher::MARKER, 'Папка опубликована без отметки «готово»');
        self::assertFileExists($path . '/css/login.min.css');
    }

    public function testAjaxRequestDoesNotStartCopying(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        try {
            $this->runAction('admin/default/check-user', [], ['username' => 'nobody']);
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        $path = Yii::$app->assetManager->getPublishedPath((string) Yii::getAlias('@Mitisk/Yii2Admin/assets'));
        self::assertDirectoryDoesNotExist($path);
    }
}
