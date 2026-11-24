<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\assets;

use Mitisk\Yii2Admin\assets\AppAsset;
use PHPUnit\Framework\TestCase;

/**
 * Слой умолчаний для обычной разметки (assets/src/admin/base.css → css/base.min.css).
 */
final class BaseStylesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../../assets/';

    public function testBaseLayerIsLoadedLastSoItBeatsBootstrapDefaults(): void
    {
        $css = (new AppAsset())->css;

        self::assertSame('css/base.min.css', end($css));
        self::assertFileExists(self::ROOT . 'css/base.min.css');
    }

    public function testEveryRuleIsScopedToAdminContent(): void
    {
        $source = (string) file_get_contents(self::ROOT . 'src/admin/base.css');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        preg_match_all('~(?:^|\})\s*([^{}@]+)\{~', $source, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $selectorList) {
            foreach (explode(",\n", trim($selectorList)) as $selector) {
                self::assertStringStartsWith(
                    ':where(.main-content-wrap)',
                    trim($selector),
                    'Правило вне рабочей области админки заденет меню, шапку и страницу входа'
                );
            }
        }
    }

    public function testBuiltFileIsNotOlderThanSource(): void
    {
        // Забытая пересборка: исходник поправили, а css/base.min.css остался прежним
        $built = (string) file_get_contents(self::ROOT . 'css/base.min.css');
        $source = (string) file_get_contents(self::ROOT . 'src/admin/base.css');

        preg_match_all('~--bs-table-[a-z-]+|\.sortableLists|\.detail-view|\.hint-block|\.error-summary~', $source, $m);
        foreach (array_unique($m[0]) as $marker) {
            self::assertStringContainsString($marker, $built, "В собранном файле нет «{$marker}»: запустите sh assets/src/build.sh");
        }
    }
}
