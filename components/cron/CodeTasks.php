<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\cron;

use Mitisk\Yii2Admin\models\CronTask;
use Mitisk\Yii2Admin\Module;
use Yii;

/**
 * Задачи планировщика из кода проекта (Module::$cronTasks).
 *
 * Расписание таких задач живёт в коде — в git, под тестами проекта, — а в таблице admin_cron_task
 * для них хранятся только состояние и журнал. sync() приводит строки к коду: создаёт новые, правит
 * изменённые, удаляет те, что из кода убрали (вместе с журналом). Включить, выключить или поменять
 * расписание задачи из кода в админке нельзя.
 */
final class CodeTasks
{
    /** Ключ: латиница, цифры, точка, дефис, подчёркивание; он же имя строки в таблице */
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

    /**
     * Приводит строки задач из кода к описаниям в коде.
     *
     * @return string[] ошибки описаний (такие задачи пропущены, их строки и журнал сохраняются)
     */
    public static function sync(): array
    {
        [$definitions, $errors, $brokenKeys] = self::definitions();

        $existing = CronTask::find()->where(['not', ['code_key' => null]])->indexBy('code_key')->all();
        foreach ($definitions as $key => $definition) {
            $task = $existing[$key] ?? new CronTask(['code_key' => $key]);
            $task->setAttributes([
                'name' => $definition['name'],
                'command' => $definition['command'],
                'schedule' => $definition['schedule'],
                'timeout' => $definition['timeout'],
                'log_mode' => $definition['log'],
                'enabled' => 1,
            ], false);
            if ($task->isNewRecord || $task->getDirtyAttributes() !== []) {
                $task->save(false);
            }
            unset($existing[$key]);
        }

        // Убранные из кода задачи удаляются с журналом; задача с ошибкой в описании остаётся как есть
        foreach ($existing as $key => $task) {
            if (!isset($brokenKeys[$key])) {
                $task->delete();
            }
        }

        foreach ($errors as $error) {
            Yii::warning('Планировщик: ' . $error, __METHOD__);
        }

        return $errors;
    }

    /**
     * Проверенные описания задач из кода.
     *
     * @return array{0: array<string, array{name: string, command: string, schedule: string, timeout: int, log: string}>, 1: string[], 2: array<string, true>}
     *         описания по ключу, ошибки, ключи задач с ошибкой в описании
     */
    public static function definitions(): array
    {
        $raw = self::raw();
        $definitions = [];
        $errors = [];
        $brokenKeys = [];

        foreach ($raw as $index => $item) {
            if (!is_array($item)) {
                $errors[] = 'Описание №' . ($index + 1) . ' — не массив.';
                continue;
            }
            $key = is_string($item['key'] ?? null) ? trim($item['key']) : '';
            if (!preg_match(self::KEY_PATTERN, $key)) {
                $errors[] = 'Описание №' . ($index + 1) . ': нужен ключ key из латиницы, цифр, «.», «-», «_» (до 64 знаков).';
                continue;
            }
            if (isset($definitions[$key]) || isset($brokenKeys[$key])) {
                $errors[] = 'Задача «' . $key . '»: такой ключ уже есть.';
                continue;
            }

            $log = $item['log'] ?? CronTask::LOG_ALL;
            $probe = new CronTask([
                'name' => is_string($item['name'] ?? null) && trim($item['name']) !== '' ? trim($item['name']) : (string) ($item['command'] ?? $key),
                'command' => is_string($item['command'] ?? null) ? $item['command'] : '',
                'schedule' => is_string($item['schedule'] ?? null) ? $item['schedule'] : '',
                'timeout' => $item['timeout'] ?? 0,
                'log_mode' => $log,
            ]);
            if (!$probe->validate(['name', 'command', 'schedule', 'timeout', 'log_mode'])) {
                $errors[] = 'Задача «' . $key . '»: ' . implode(' ', $probe->getFirstErrors());
                $brokenKeys[$key] = true;
                continue;
            }

            $definitions[$key] = [
                'name' => (string) $probe->name,
                'command' => (string) $probe->command,
                'schedule' => (string) $probe->schedule,
                'timeout' => (int) $probe->timeout,
                'log' => (string) $probe->log_mode,
            ];
        }

        return [$definitions, $errors, $brokenKeys];
    }

    /**
     * @return array<int, mixed>
     */
    private static function raw(): array
    {
        $module = Yii::$app !== null ? Yii::$app->getModule('admin') : null;
        if (!$module instanceof Module) {
            return [];
        }
        $tasks = $module->cronTasks;
        if (is_callable($tasks)) {
            $tasks = $tasks();
        }

        return is_array($tasks) ? array_values($tasks) : [];
    }
}
