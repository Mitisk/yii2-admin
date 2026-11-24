<?php

declare(strict_types=1);

/**
 * Bootstrap тестов пакета.
 *
 * 1. Находит автозагрузчик: свой vendor пакета или vendor приложения, в которое пакет подключён
 *    (packages/yii2-admin → ../../vendor). Путь можно задать явно: YII2ADMIN_AUTOLOAD.
 * 2. Применяет к тестовой БД миграции модуля и тестовые таблицы (идемпотентно).
 * 3. Записывает GENERAL.version = Module::VERSION, иначе модуль уводит на страницу обновления.
 */

use Mitisk\Yii2Admin\Module;
use Mitisk\Yii2Admin\tests\TestApp;

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

$candidates = array_filter([
    getenv('YII2ADMIN_AUTOLOAD') ?: null,
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
]);
$autoload = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}
if ($autoload === null) {
    fwrite(STDERR, "Не найден vendor/autoload.php. Выполните composer install или задайте YII2ADMIN_AUTOLOAD.\n");
    exit(1);
}

$loader = require $autoload;
// Тестовые классы пакета — по тому же psr-4, но из этой папки, а не через junction vendor/.
$loader->addPsr4('Mitisk\\Yii2Admin\\tests\\', __DIR__ . '/', true);

require_once dirname($autoload) . '/yiisoft/yii2/Yii.php';

Yii::setAlias('@Mitisk/Yii2Admin', dirname(__DIR__));

TestApp::console();
ob_start();
$code = Yii::$app->runAction('migrate/up', [
    'interactive' => 0,
    'color' => false,
    'migrationPath' => [
        '@Mitisk/Yii2Admin/migrations',
        '@Mitisk/Yii2Admin/tests/_app/migrations',
    ],
]);
$output = (string) ob_get_clean();
if ($code !== 0) {
    fwrite(STDERR, "Миграции тестовой БД не применились:\n" . $output);
    exit(1);
}

Yii::$app->settings->set('GENERAL', 'version', Module::VERSION);
TestApp::destroy();
