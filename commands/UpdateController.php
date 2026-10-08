<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\commands;

use Mitisk\Yii2Admin\components\SelfUpdateService;
use Mitisk\Yii2Admin\Module;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Обновление модуля админки через composer.
 *
 * Подключение в консольном конфиге:
 * ```php
 * 'bootstrap' => ['admin'],
 * 'modules' => ['admin' => ['class' => \Mitisk\Yii2Admin\Module::class]],
 * ```
 *
 * Использование:
 *  - `php yii admin/update`        — composer update + миграции + кэш + версия;
 *  - `php yii admin/update/check`  — показать, что найдено в окружении;
 *  - `php yii admin/update/finish` — только пост-шаги (вызывается автоматически).
 */
class UpdateController extends Controller
{
    /** @var bool Внутренний флаг: запуск инициирован из админки. */
    public $fromWeb = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), $actionID === 'index' ? ['fromWeb'] : []);
    }

    /**
     * Обновляет пакет mitisk/yii2-admin и применяет миграции.
     */
    public function actionIndex(): int
    {
        $service = Yii::createObject(SelfUpdateService::class);
        $code = $service->run(
            fn(string $line) => $this->stdout($line . PHP_EOL),
            (bool)$this->fromWeb
        );
        $this->stdout($code === 0 ? "Готово.\n" : "Завершено с ошибкой (код {$code}).\n");
        return $code;
    }

    /**
     * Пост-шаги после замены файлов (миграции, кэш, версия). Запускается в новом процессе.
     */
    public function actionFinish(): int
    {
        $service = Yii::createObject(SelfUpdateService::class);
        return $service->finish(fn(string $line) => $this->stdout($line . PHP_EOL));
    }

    /**
     * Показывает, что найдено в окружении, без запуска обновления.
     */
    public function actionCheck(): int
    {
        $service = Yii::createObject(SelfUpdateService::class);
        $this->stdout('Версия модуля:   ' . Module::VERSION . PHP_EOL);
        $this->stdout('Последний релиз: ' . (Module::getLatestRelease() ?? 'неизвестно') . PHP_EOL);
        $this->stdout('Ограничение:     ' . ($service->getConstraint() ?? 'не найдено') . PHP_EOL);
        $this->stdout('Корень проекта:  ' . $service->getProjectRoot() . PHP_EOL);
        $this->stdout('PHP CLI:         ' . ($service->findPhp() ?? 'не найден') . PHP_EOL);
        $composer = $service->findComposer();
        $this->stdout('Composer:        ' . ($composer ? implode(' ', $composer) : 'не найден') . PHP_EOL);
        $this->stdout('Скрипт yii:      ' . ($service->getYiiScript() ?? 'не найден') . PHP_EOL);
        $this->stdout('--no-dev:        ' . ($service->isInstalledWithoutDev() ? 'да' : 'нет') . PHP_EOL);
        $this->stdout(PHP_EOL . 'Проверки для запуска из админки:' . PHP_EOL);
        $ok = true;
        foreach ($service->checkBackground() as $check) {
            $ok = $ok && $check['ok'];
            $this->stdout(($check['ok'] ? '  [ok]   ' : '  [fail] ') . $check['label'] . PHP_EOL);
            if (!$check['ok']) {
                $this->stdout('         ' . $check['hint'] . PHP_EOL);
            }
        }
        $state = $service->getState();
        $this->stdout(PHP_EOL . 'Состояние: ' . $state['status']
            . ($state['message'] ? ' — ' . $state['message'] : '') . PHP_EOL);
        return $ok ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
