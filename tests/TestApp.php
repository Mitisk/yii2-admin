<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests;

use Mitisk\Yii2Admin\components\SettingsComponent;
use Mitisk\Yii2Admin\Module;
use Mitisk\Yii2Admin\tests\_app\ArraySession;
use Yii;
use yii\caching\ArrayCache;
use yii\console\controllers\MigrateController;
use yii\db\Connection;
use yii\rbac\DbManager;

/**
 * Приложения Yii для тестов пакета: консольное (миграции в bootstrap) и веб (тесты экшенов).
 */
final class TestApp
{
    public static function console(): void
    {
        new \yii\console\Application([
            'id' => 'yii2admin-test-console',
            'basePath' => __DIR__ . '/_app',
            'vendorPath' => self::vendorPath(),
            'runtimePath' => self::runtimePath(),
            'components' => self::components(),
            'controllerMap' => [
                'migrate' => ['class' => MigrateController::class],
            ],
        ]);
    }

    public static function web(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/_app/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';

        new \yii\web\Application([
            'id' => 'yii2admin-test-web',
            'basePath' => __DIR__ . '/_app',
            'vendorPath' => self::vendorPath(),
            'runtimePath' => self::runtimePath(),
            'language' => 'ru-RU',
            'bootstrap' => ['admin'],
            'modules' => [
                'admin' => ['class' => Module::class],
            ],
            'components' => self::components() + [
                'request' => [
                    'cookieValidationKey' => 'yii2admin-test',
                    'enableCsrfValidation' => false,
                    // Иначе Module::beforeAction в CLI не подставляет adminUser вместо user.
                    'isConsoleRequest' => false,
                    'scriptUrl' => '/index.php',
                    'hostInfo' => 'http://localhost',
                ],
                'session' => ['class' => ArraySession::class],
                'urlManager' => [
                    'enablePrettyUrl' => true,
                    'showScriptName' => false,
                    'suffix' => '/',
                ],
            ],
        ]);

        // Обработчик ошибок Yii превращает предупреждения PHP в исключения и перехватывает
        // необработанные — в тестах ошибки должен видеть PHPUnit.
        Yii::$app->errorHandler->unregister();
    }

    public static function destroy(): void
    {
        if (Yii::$app !== null && Yii::$app->has('db', true)) {
            Yii::$app->db->close();
        }
        Yii::$app = null;
        unset($_SESSION);
        $_GET = $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function components(): array
    {
        return [
            'db' => [
                'class' => Connection::class,
                'dsn' => (string) getenv('YII2ADMIN_TEST_DSN'),
                'username' => (string) getenv('YII2ADMIN_TEST_USER'),
                'password' => (string) getenv('YII2ADMIN_TEST_PASSWORD'),
                'charset' => 'utf8mb4',
            ],
            'cache' => ['class' => ArrayCache::class],
            'settings' => ['class' => SettingsComponent::class],
            'authManager' => [
                'class' => DbManager::class,
                'defaultRoles' => ['guest', 'user'],
            ],
        ];
    }

    private static function vendorPath(): string
    {
        $vendor = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);

        return $vendor;
    }

    private static function runtimePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yii2admin-tests';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }
}
