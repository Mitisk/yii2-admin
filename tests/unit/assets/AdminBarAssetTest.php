<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\assets;

use Mitisk\Yii2Admin\assets\AdminBarAsset;
use Mitisk\Yii2Admin\assets\AppAsset;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;

/**
 * Панель на сайте публикуется отдельно от ассетов админки: иначе страница сайта, открытая
 * раньше админки, кладёт в общую папку публикации только файлы панели и /admin/ остаётся без CSS.
 */
final class AdminBarAssetTest extends TestCase
{
    private const FILES = [
        'js/admin-bar.min.js',
        'js/admin-bar-loader.min.js',
        'js/admin-bar-blocks.min.js',
        'css/admin-bar.min.css',
    ];

    public function testBarIsPublishedToItsOwnFolder(): void
    {
        $am = Yii::$app->assetManager;

        self::assertNotSame($am->getBundle(AppAsset::class)->baseUrl, $am->getBundle(AdminBarAsset::class)->baseUrl);
    }

    public function testBarFolderHoldsEveryFileTheWidgetLinks(): void
    {
        $source = (string) Yii::getAlias((new AdminBarAsset())->sourcePath);

        foreach (self::FILES as $file) {
            self::assertFileExists($source . '/' . $file);
        }
    }

    public function testBarFolderIsPublishedWhole(): void
    {
        self::assertArrayNotHasKey('only', (new AdminBarAsset())->publishOptions);
    }
}
