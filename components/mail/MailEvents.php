<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\mail;

use Yii;

/**
 * Письма-события проекта, известные админке (настройка модуля `mailEvents`).
 *
 * Зная событие, страница шаблона показывает его переменные с описаниями из кода, не даёт сменить
 * код шаблона и умеет вернуть исходный текст; список шаблонов показывает письма, которые ещё
 * ни разу не отправлялись.
 *
 * ```php
 * 'admin' => [
 *     'class' => \Mitisk\Yii2Admin\Module::class,
 *     'mailEvents' => [\app\components\mail\MailEvent::class],   // enum с MailEventInterface, объекты или callable
 * ],
 * ```
 */
final class MailEvents
{
    /** @var array<string, MailEventInterface>|null */
    private ?array $events = null;

    /** @var array<int, mixed>|callable */
    private $source;

    /**
     * @param array<int, mixed>|callable $source классы enum, объекты MailEventInterface или callable, который их возвращает
     */
    public function __construct($source = [])
    {
        $this->source = $source;
    }

    public static function fromModule(): self
    {
        $module = Yii::$app->getModule('admin');

        return new self($module !== null && isset($module->mailEvents) ? $module->mailEvents : []);
    }

    /**
     * @return array<string, MailEventInterface> код шаблона => событие
     */
    public function all(): array
    {
        if ($this->events !== null) {
            return $this->events;
        }
        $this->events = [];
        try {
            $source = is_callable($this->source) ? ($this->source)() : $this->source;
            foreach (is_iterable($source) ? $source : [] as $item) {
                foreach ($this->expand($item) as $event) {
                    $this->events[$event->slug()] = $event;
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('Письма-события из настройки mailEvents не прочитаны: ' . $e->getMessage(), __METHOD__);
        }

        return $this->events;
    }

    public function find(string $slug): ?MailEventInterface
    {
        return $this->all()[$slug] ?? null;
    }

    /**
     * @return MailEventInterface[]
     */
    private function expand(mixed $item): array
    {
        if ($item instanceof MailEventInterface) {
            return [$item];
        }
        if (is_string($item) && enum_exists($item) && is_subclass_of($item, MailEventInterface::class)) {
            return $item::cases();
        }
        Yii::warning('mailEvents: ' . (is_string($item) ? $item : get_debug_type($item)) . ' — не событие письма', __METHOD__);

        return [];
    }
}
