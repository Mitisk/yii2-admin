<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\assets;

use Mitisk\Yii2Admin\assets\PackageAsset;
use Mitisk\Yii2Admin\components\AssetPublisher;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\helpers\FileHelper;
use yii\web\AssetBundle;
use yii\web\AssetManager;

/**
 * Публикация ассетов пакета: в web/assets уходит только нужное, а недокопированная папка
 * (запрос оборвался по лимиту времени) восстанавливается сама.
 */
final class PackageAssetPublishTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'admin-assets-' . bin2hex(random_bytes(5));
        mkdir($this->dir . '/web', 0777, true);
        mkdir($this->dir . '/source/css', 0777, true);
        file_put_contents($this->dir . '/source/css/style.css', 'body{}');
        file_put_contents($this->dir . '/source/app.js', 'ok');
    }

    protected function tearDown(): void
    {
        FileHelper::removeDirectory($this->dir);
        parent::tearDown();
    }

    public function testEveryBundleOfTheSharedFolderUsesTheSamePublishOptions(): void
    {
        foreach ($this->packageBundles() as $class) {
            $bundle = new $class();
            if (rtrim((string) $bundle->sourcePath, '/\\') !== rtrim((string) Yii::getAlias('@Mitisk/Yii2Admin/assets'), '/\\')) {
                continue;
            }
            self::assertInstanceOf(PackageAsset::class, $bundle, $class . ' публикует общую папку со своими настройками: кто опубликует первым, тот и решит, что в ней окажется');
            self::assertSame(PackageAsset::PUBLISH_OPTIONS, $bundle->publishOptions, $class . ' переопределяет настройки публикации общей папки');
        }
    }

    public function testPublishedFolderHasWhatBundlesNeedAndNothingHeavy(): void
    {
        $manager = $this->manager();
        // Через сам бандл: папку публикует тот, кто первым попал на страницу, — обычно AppAsset
        $app = new \Mitisk\Yii2Admin\assets\AppAsset();
        $app->publish($manager);
        $path = $app->basePath;

        foreach ($this->packageBundles() as $class) {
            $bundle = new $class();
            if (!$bundle instanceof PackageAsset) {
                continue;
            }
            foreach (array_merge($bundle->css, $bundle->js) as $file) {
                $file = is_array($file) ? $file[0] : $file;
                if (preg_match('~^(https?:)?//~', $file)) {
                    continue;
                }
                self::assertFileExists($path . '/' . $file, $class . ' подключает файл, которого нет в опубликованной папке');
            }
        }
        // Файлы, на которые ссылаются стили и шаблоны напрямую
        foreach (['component/fa/webfonts', 'component/form-builder/lang', 'img/logo.webp', 'img/no_avatar.png', 'node_modules/ace-builds/src-noconflict/mode-html.min.js'] as $needed) {
            self::assertFileExists($path . '/' . $needed);
        }
        foreach (['component/fa/svgs', 'component/fa/js', 'component/fa/sprites', 'node_modules/ace-builds/src-min', 'node_modules/ace-builds/src', 'src', 'AppAsset.php'] as $heavy) {
            self::assertFileDoesNotExist($path . '/' . $heavy);
        }

        $files = iterator_count(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)));
        self::assertLessThan(3000, $files, 'В web/assets снова копируются десятки тысяч файлов');
    }

    public function testMissingFolderIsPublishedAndMarkedComplete(): void
    {
        $manager = $this->manager();

        self::assertTrue(AssetPublisher::ensure($manager, $this->dir . '/source'));

        $path = $manager->getPublishedPath($this->dir . '/source');
        self::assertFileExists($path . '/css/style.css');
        self::assertFileExists($path . '/' . AssetPublisher::MARKER);
        self::assertFalse(AssetPublisher::ensure($manager, $this->dir . '/source'), 'Готовая папка копируется повторно');
    }

    public function testAbandonedPartialFolderIsRebuilt(): void
    {
        $manager = $this->manager();
        $path = $manager->getPublishedPath($this->dir . '/source');
        mkdir($path . '/css', 0777, true);
        file_put_contents($path . '/css/half.css', '');
        touch($path, time() - AssetPublisher::STALE_AFTER - 60);

        self::assertTrue(AssetPublisher::ensure($manager, $this->dir . '/source'));

        self::assertFileExists($path . '/app.js', 'Недокопированная папка не восстановлена');
        self::assertFileDoesNotExist($path . '/css/half.css');
        self::assertFileExists($path . '/' . AssetPublisher::MARKER);
    }

    public function testFolderBeingCopiedRightNowIsLeftAlone(): void
    {
        $manager = $this->manager();
        $path = $manager->getPublishedPath($this->dir . '/source');
        mkdir($path, 0777, true);
        file_put_contents($path . '/half.css', '');

        self::assertFalse(AssetPublisher::ensure($manager, $this->dir . '/source'));
        self::assertFileExists($path . '/half.css');
    }

    public function testSymlinkModeIsNotTouched(): void
    {
        $manager = $this->manager(['linkAssets' => true]);

        self::assertFalse(AssetPublisher::ensure($manager, $this->dir . '/source'));
        self::assertDirectoryDoesNotExist($manager->getPublishedPath($this->dir . '/source'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function manager(array $config = []): AssetManager
    {
        return new AssetManager($config + [
            'basePath' => $this->dir . '/web',
            'baseUrl' => '/assets',
            'linkAssets' => false,
            'forceCopy' => false,
        ]);
    }

    /**
     * @return string[] классы бандлов пакета
     */
    private function packageBundles(): array
    {
        $classes = [];
        foreach (glob(dirname(__DIR__, 3) . '/assets/*.php') ?: [] as $file) {
            $class = 'Mitisk\\Yii2Admin\\assets\\' . basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, AssetBundle::class) && !(new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }
        self::assertNotEmpty($classes);

        return $classes;
    }
}
