<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\components;

use Mitisk\Yii2Admin\components\SelfUpdateService;
use Mitisk\Yii2Admin\tests\TestCase;

/**
 * Код завершения команд самообновления (composer, admin/update/finish).
 *
 * В PHP до 8.3 на Linux настоящий код отдаёт только первый вызов proc_get_status() после выхода
 * процесса, а proc_close() после него возвращает -1. Успешный composer выглядел как «код -1».
 */
final class SelfUpdateExitCodeTest extends TestCase
{
    public function testCodeSeenByStatusWinsOverMinusOneFromClose(): void
    {
        self::assertSame(0, SelfUpdateService::exitCode(0, -1), 'Успешный composer принят за ошибку');
        self::assertSame(2, SelfUpdateService::exitCode(2, -1));
    }

    public function testCloseIsUsedWhenStatusDidNotSeeTheExit(): void
    {
        self::assertSame(0, SelfUpdateService::exitCode(null, 0));
        self::assertSame(3, SelfUpdateService::exitCode(null, 3));
    }

    public function testProcessKilledBySignalIsAFailure(): void
    {
        self::assertNotSame(0, SelfUpdateService::exitCode(-1, -1));
    }

    public function testRealProcessesReportTheirExitCodes(): void
    {
        $service = new SelfUpdateService();
        $exec = new \ReflectionMethod($service, 'exec');
        $exec->setAccessible(true);
        $lines = [];
        $out = static function (string $line) use (&$lines): void {
            $lines[] = $line;
        };

        $ok = $exec->invoke($service, [PHP_BINARY, '-r', 'echo "готово\n";'], sys_get_temp_dir(), [], $out);
        $failed = $exec->invoke($service, [PHP_BINARY, '-r', 'fwrite(STDERR, "плохо\n"); exit(3);'], sys_get_temp_dir(), [], $out);

        self::assertSame(0, $ok);
        self::assertSame(3, $failed);
        self::assertContains('готово', $lines);
        self::assertContains('плохо', $lines);
    }
}
