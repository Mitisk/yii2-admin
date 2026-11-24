<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Mitisk\Waf\Config;
use Mitisk\Waf\IpTools;
use Mitisk\Waf\LightweightWaf;
use Mitisk\Waf\WafControl;
use Yii;

/**
 * Связка с пакетом mitisk/yii2-lightweight-waf (необязательная зависимость).
 *
 * Пакет установлен — в админке появляется раздел «Защита» (/admin/waf/): настройки, блокировки,
 * журнал. Не установлен — ничего не меняется. WAF работает до Yii и базы, поэтому настройки
 * пишутся в его файл (runtime/waf_blocks/settings.php) через WafControl; копия хранится в базе
 * (HIDDEN.waf_settings) и возвращает файл, если папку runtime очистили при выкладке.
 */
final class WafBridge
{
    /** Версия интерфейса WafControl, с которой умеет работать этот раздел. */
    public const REQUIRED_API = 1;

    private const SETTINGS_SECTION = 'HIDDEN';
    private const SETTINGS_KEY = 'waf_settings';

    /** @internal Класс-точка входа WAF; меняется только в тестах, чтобы изобразить «пакет не установлен». */
    public static string $entryClass = 'Mitisk\\Waf\\LightweightWaf';

    /** Пакет WAF установлен в проекте. */
    public static function isInstalled(): bool
    {
        return class_exists(self::$entryClass);
    }

    /** Пакет установлен и его версия умеет управляться из панели (2.1+). */
    public static function isSupported(): bool
    {
        return self::isInstalled()
            && class_exists(WafControl::class)
            && WafControl::API >= self::REQUIRED_API;
    }

    /** WAF отработал в этом запросе, то есть подключён в web/index.php. */
    public static function isRunning(): bool
    {
        return self::isSupported() && LightweightWaf::config() !== null;
    }

    /**
     * Управление хранилищем WAF: папка, с которой он запущен в index.php, а в консоли — @runtime/waf_blocks.
     */
    public static function control(): WafControl
    {
        $control = WafControl::current((string)Yii::getAlias('@runtime'));
        if ($control === null) {
            throw new \RuntimeException('Не удалось определить папку хранилища WAF.');
        }

        return $control;
    }

    /**
     * Действующий конфиг: с которым WAF отработал в этом запросе, а если он не запускался —
     * значения по умолчанию плюс сохранённые настройки.
     */
    public static function config(): Config
    {
        $config = LightweightWaf::config();
        if ($config === null) {
            $config = Config::fromArray([]);
            $config->applyAdminSettings(self::control()->settings());
        }

        return $config;
    }

    /** Адрес текущего посетителя так, как его видит WAF (с учётом доверенных прокси из кода). */
    public static function clientIp(): string
    {
        $config = self::config();

        return IpTools::clientIp($_SERVER, $config->trustedHeaders, $config->trustedProxies);
    }

    /**
     * Сайт стоит за прокси, а WAF об этом не знает: в запросе есть заголовок прокси, но адресом
     * посетителя WAF считает адрес соединения (то есть сам прокси). Тогда все посетители для него —
     * один адрес, и первый же сканер заблокирует всех.
     *
     * @return string|null адрес прокси, который WAF принимает за посетителя, или null — всё в порядке
     */
    public static function untrustedProxy(): ?string
    {
        $hasProxyHeader = false;
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_FORWARDED'] as $key) {
            if (!empty($_SERVER[$key])) {
                $hasProxyHeader = true;
                break;
            }
        }
        if (!$hasProxyHeader) {
            return null;
        }
        $remote = IpTools::normalize((string)($_SERVER['REMOTE_ADDR'] ?? ''));

        return $remote !== null && self::clientIp() === $remote ? $remote : null;
    }

    /**
     * Копия настроек в базе; [] — из админки ничего не сохраняли.
     *
     * @return array<string, mixed>
     */
    public static function storedSettings(): array
    {
        $value = Yii::$app->settings->get(self::SETTINGS_SECTION, self::SETTINGS_KEY, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Проверяет настройки, пишет файл WAF и копию в базу.
     *
     * @param array<string, mixed> $settings
     * @param string[] $safePaths пути, которые свои шаблоны не должны блокировать
     * @return array<string, mixed> сохранённые настройки
     * @throws \Mitisk\Waf\SettingsException настройки неверны, ничего не сохранено
     */
    public static function save(array $settings, array $safePaths): array
    {
        $saved = self::control()->saveSettings($settings, $safePaths);
        Yii::$app->settings->set(
            self::SETTINGS_SECTION,
            self::SETTINGS_KEY,
            json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'json'
        );

        return $saved;
    }

    /** Убирает настройки админки: WAF снова работает только по конфигу из web/index.php. */
    public static function reset(): void
    {
        self::control()->resetSettings();
        Yii::$app->settings->set(self::SETTINGS_SECTION, self::SETTINGS_KEY, '[]', 'json');
    }

    /**
     * Возвращает файл настроек WAF из копии в базе, если он пропал (runtime очистили при выкладке).
     * Вызывается на запросах вошедшего администратора; цена — чтение одного маленького файла.
     *
     * @return bool файл был восстановлен
     */
    public static function heal(): bool
    {
        try {
            if (!self::isSupported()) {
                return false;
            }
            $stored = self::storedSettings();
            if ($stored === [] || self::control()->settings() !== []) {
                return false;
            }
            self::control()->saveSettings($stored, []);
            Yii::warning('Файл настроек WAF восстановлен из копии в базе.', __METHOD__);

            return true;
        } catch (\Throwable $e) {
            Yii::warning('Настройки WAF не восстановлены: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }
}
