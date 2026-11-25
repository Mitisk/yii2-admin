<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\audit;

use Yii;

/**
 * Названия действий журнала: встроенные (создал, изменил, удалил) и действия проекта
 * из настройки модуля `auditActions`.
 *
 * Проект пишет в журнал свои действия — `AuditService::log('block', $user)`; без названия
 * такое действие показывается кодом. Название задаётся один раз в конфиге модуля:
 *
 * ```php
 * 'auditActions' => [
 *     'block' => ['label' => 'заблокировал', 'tone' => 'red'],
 *     'awards' => 'изменил награды',
 * ],
 * ```
 */
final class AuditActions
{
    public const TONES = ['green', 'blue', 'red', 'orange', 'gray'];

    /** Очистка журнала: пишется самим журналом. */
    public const CLEAR = 'audit_clear';

    private const BUILT_IN = [
        'create' => ['label' => 'создал', 'tone' => 'green'],
        'update' => ['label' => 'изменил', 'tone' => 'blue'],
        'delete' => ['label' => 'удалил', 'tone' => 'red'],
        self::CLEAR => ['label' => 'очистил', 'tone' => 'red'],
    ];

    /** @var array<string, array{label: string, tone: string}> */
    private array $actions;

    /**
     * @param array<string, string|array<string, mixed>> $custom Действия проекта: код => название или ['label', 'tone']
     */
    public function __construct(array $custom = [])
    {
        $this->actions = self::BUILT_IN;
        foreach ($custom as $code => $definition) {
            $label = is_array($definition) ? ($definition['label'] ?? '') : $definition;
            if (!is_string($code) || !is_string($label) || trim($label) === '') {
                continue;
            }
            $tone = is_array($definition) ? ($definition['tone'] ?? 'gray') : 'gray';
            $this->actions[$code] = [
                'label' => trim($label),
                'tone' => in_array($tone, self::TONES, true) ? $tone : 'gray',
            ];
        }
    }

    /** Действия из конфига модуля admin. */
    public static function fromModule(): self
    {
        $module = Yii::$app->getModule('admin');
        $custom = $module !== null && isset($module->auditActions) && is_array($module->auditActions)
            ? $module->auditActions
            : [];

        return new self($custom);
    }

    /** «изменил», «заблокировал»; у действия без названия — его код. */
    public function label(string $action): string
    {
        return $this->actions[$action]['label'] ?? $action;
    }

    public function tone(string $action): string
    {
        return $this->actions[$action]['tone'] ?? 'gray';
    }

    public function isKnown(string $action): bool
    {
        return isset($this->actions[$action]);
    }

    /**
     * Варианты фильтра «Действие» для кодов, которые есть в журнале.
     *
     * @param string[] $codes
     * @return array<string, string> код => «Изменил»
     */
    public function options(array $codes): array
    {
        $options = [];
        foreach ($codes as $code) {
            $code = (string) $code;
            if ($code === '') {
                continue;
            }
            $label = $this->label($code);
            $options[$code] = mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
        }
        asort($options, SORT_LOCALE_STRING);

        return $options;
    }
}
