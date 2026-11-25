<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\log;

use Mitisk\Yii2Admin\components\log\LogMask;
use Mitisk\Yii2Admin\components\log\LogParser;
use Mitisk\Yii2Admin\components\log\SystemLog;
use PHPUnit\Framework\TestCase;

/**
 * Разбор лога приложения для вкладки «Ошибки сайта»: записи, группы, скрытие секретов, чтение файлов.
 */
final class LogParserTest extends TestCase
{
    private const LOG = <<<'LOG'
2026-11-24 10:00:00 [10.0.0.1][5][abcdefabcdefabcdefabcdefab][error][yii\web\HttpException:404] yii\web\NotFoundHttpException: Диалог не найден in /var/www/site/modules/chat/ChatActions.php:234
Stack trace:
#0 /var/www/site/modules/chat/ChatActions.php(196): app\Chat->dialogOr404(2)
#1 {main}
2026-11-24 10:00:05 [10.0.0.2][7][-][error][yii\web\HttpException:404] yii\web\NotFoundHttpException: Диалог не найден in /var/www/site/modules/chat/ChatActions.php:234
Stack trace:
#0 {main}
2026-11-25 09:00:00 [127.0.0.1][9][-][warning][yii\web\User::renewAuthStatus] Invalid session auth key attempted for user '1' from 127.0.0.1: 18Z3hxhK_2ProhYy3iMpOP78ieTwNAHz
2026-11-25 09:00:01 [127.0.0.1][1][-][warning][yii\web\User::renewAuthStatus] Invalid session auth key attempted for user '9' from 127.0.0.1: fDVIjGjP2_LrAIM-C_mfbGKKBLKhO0-H
2026-11-25 09:10:00 [-][-][-][error][yii\di\NotInstantiableException] ReflectionException: Class "app\Missing" does not exist in /var/www/site/vendor/yiisoft/yii2/di/Container.php:517
2026-11-25 09:20:00 [10.0.0.3][-][-][error][yii\web\HttpException:404] yii\base\InvalidRouteException: Unable to resolve the request "assets/cfcf53f7/js/a.js". in /var/www/site/vendor/yiisoft/yii2/base/Module.php:556
2026-11-25 09:20:01 [10.0.0.3][-][-][error][yii\web\HttpException:404] yii\base\InvalidRouteException: Unable to resolve the request "assets/0a1b2c3d/css/b.css". in /var/www/site/vendor/yiisoft/yii2/base/Module.php:556
LOG;

    public function testEntryIsSplitIntoPartsWithPathsFromProjectRoot(): void
    {
        $entries = (new LogParser('/var/www/site'))->parse(self::LOG);

        self::assertCount(7, $entries);
        $first = $entries[0];
        self::assertSame('Диалог не найден', $first['title']);
        self::assertSame('NotFoundHttpException', $first['class']);
        self::assertSame('modules/chat/ChatActions.php:234', $first['where']);
        self::assertSame('error', $first['level']);
        self::assertSame(404, $first['code']);
        self::assertTrue($first['noise'], 'Ошибка посетителя (4xx) — не поломка сайта');
        self::assertSame('10.0.0.1', $first['ip']);
        self::assertSame('5', $first['user']);
        self::assertStringContainsString('#0 modules/chat/ChatActions.php(196)', $first['full']);
        self::assertStringNotContainsString('/var/www/site', $first['full']);

        self::assertSame('', $entries[4]['ip'], 'Прочерк в префиксе — значения нет');
        self::assertFalse($entries[4]['noise']);
        self::assertNull($entries[4]['code']);
    }

    public function testSameProblemIsOneGroupWithCounterAndLastCases(): void
    {
        $parser = new LogParser('/var/www/site');
        $groups = $parser->groups($parser->parse(self::LOG), strtotime('2026-11-25 12:00:00'));

        $byTitle = [];
        foreach ($groups as $group) {
            $byTitle[$group['title']] = $group;
        }

        self::assertCount(4, $groups, 'Семь записей — четыре проблемы');
        self::assertSame(2, $byTitle['Диалог не найден']['count']);
        self::assertSame(0, $byTitle['Диалог не найден']['today']);
        self::assertSame(strtotime('2026-11-24 10:00:00'), $byTitle['Диалог не найден']['first']);
        self::assertSame(strtotime('2026-11-24 10:00:05'), $byTitle['Диалог не найден']['last']);
        self::assertSame('7', $byTitle['Диалог не найден']['samples'][0]['user'], 'Последний случай — первым');

        // Разные адреса ассетов и разные ключи разных пользователей — одна проблема
        $route = array_values(array_filter($groups, static fn (array $g): bool => $g['class'] === 'InvalidRouteException'));
        self::assertCount(1, $route);
        self::assertSame(2, $route[0]['count']);
        $key = array_values(array_filter($groups, static fn (array $g): bool => $g['level'] === 'warning'));
        self::assertCount(1, $key);
        self::assertSame(2, $key[0]['count']);
        self::assertSame(2, $key[0]['today']);

        self::assertSame($route[0]['id'], $groups[0]['id'], 'Сверху — проблема, которая случилась последней');
    }

    public function testSecretsAreHiddenInTitlesAndStacks(): void
    {
        $entries = (new LogParser())->parse(self::LOG);
        $text = json_encode($entries, JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('18Z3hxhK_2ProhYy3iMpOP78ieTwNAHz', $text);
        self::assertStringNotContainsString('fDVIjGjP2_LrAIM-C_mfbGKKBLKhO0-H', $text);
        self::assertStringContainsString('18Z3••••', $entries[2]['title']);
        self::assertStringNotContainsString('abcdefabcdefabcdefabcdefab', $text, 'Идентификатор сессии из префикса наружу не уходит');
    }

    public function testMaskKeepsOrdinaryNamesAndPaths(): void
    {
        self::assertSame(
            'Migration m261125_120000_create_admin_cron_tables failed in vendor/mitisk/yii2-admin/migrations/m261125_120000_create_admin_cron_tables.php:12',
            LogMask::mask('Migration m261125_120000_create_admin_cron_tables failed in vendor/mitisk/yii2-admin/migrations/m261125_120000_create_admin_cron_tables.php:12')
        );
        self::assertSame('GET /pay/?token=abcd••••&id=5', LogMask::mask('GET /pay/?token=abcdef123456&id=5'));
        self::assertSame('password: hunt••••', LogMask::mask('password: hunter2000'));
        self::assertSame('session 0123••••', LogMask::mask('session 0123456789abcdef0123456789abcdef'));
    }

    public function testRequestDumpGivesPageAddressAndIsNotShownItself(): void
    {
        $log = <<<'LOG'
2026-11-25 10:00:00 [10.0.0.1][5][s1][error][application] Что-то сломалось
2026-11-25 10:00:00 [10.0.0.1][5][s1][info][application] $_GET = []

$_COOKIE = [
    '_identity' => 'secret-cookie-value'
]

$_SERVER = [
    'REQUEST_METHOD' => 'POST'
    'REQUEST_URI' => '/cabinet/guests/save/?id=7'
]
2026-11-25 10:00:02 [10.0.0.9][-][-][error][application] Другой запрос без дампа
LOG;

        $entries = (new LogParser())->parse($log);

        self::assertCount(2, $entries, 'Дамп окружения — не отдельная запись');
        self::assertSame('/cabinet/guests/save/?id=7', $entries[0]['url']);
        self::assertSame('POST', $entries[0]['method']);
        self::assertSame('', $entries[1]['url']);
        self::assertStringNotContainsString('secret-cookie-value', json_encode($entries, JSON_UNESCAPED_UNICODE));
    }

    public function testMessageStartingWithBracketKeepsItsBrackets(): void
    {
        $entries = (new LogParser())->parse('2026-11-25 10:00:00 [-][-][-][warning][app][cron][purge] задача пропущена');

        self::assertSame('app', $entries[0]['category']);
        self::assertSame('[cron][purge] задача пропущена', $entries[0]['title']);
    }

    public function testReaderTakesNewestFilesFirstWithinBudgetAndStartsFromWholeEntry(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yii2admin-syslog-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . DIRECTORY_SEPARATOR . 'app.log';
        // Строки длиннее наименьшего предела чтения (1 КБ)
        $line = static fn (string $time, string $text): string => "2026-11-25 {$time} [-][-][-][error][app] {$text} "
            . str_repeat('x', 1200) . "\n";
        file_put_contents($file . '.1', $line('08:00:00', 'старая один') . $line('08:00:01', 'старая два'));
        file_put_contents($file, $line('09:00:00', 'новая'));

        try {
            $all = (new SystemLog($file))->read();
            self::assertSame(2, $all['files']);
            self::assertFalse($all['truncated']);
            self::assertStringContainsString('старая один', $all['chunks'][0], 'Куски идут от старых к новым');
            self::assertStringContainsString('новая', $all['chunks'][1]);

            // Предела хватает на новый файл и на хвост архивного: оборванная запись отбрасывается
            $budget = strlen($line('09:00:00', 'новая')) + strlen($line('08:00:01', 'старая два')) + 10;
            $part = (new SystemLog($file, $budget))->read();
            self::assertTrue($part['truncated']);
            $text = implode('', $part['chunks']);
            self::assertStringContainsString('новая', $text);
            self::assertStringContainsString('старая два', $text);
            self::assertStringNotContainsString('старая один', $text);
            self::assertStringStartsWith('2026-11-25 08:00:01', $part['chunks'][0]);

            self::assertSame([], (new SystemLog($dir . DIRECTORY_SEPARATOR . 'none.log'))->read()['chunks']);
        } finally {
            @unlink($file);
            @unlink($file . '.1');
            @rmdir($dir);
        }
    }
}
