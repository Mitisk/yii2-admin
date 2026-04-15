<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use yii\base\Component;
use Mitisk\Yii2Admin\models\Settings;

/**
 * Компонент-фасад для работы с настройками админ-панели.
 *
 * Регистрируется как `Yii::$app->settings` и делегирует операции
 * чтения/записи модели {@see Settings}, скрывая прямой доступ к ActiveRecord.
 *
 * Пример:
 * ```php
 * Yii::$app->settings->set('GENERAL', 'site_name', 'My Site');
 * $email = Yii::$app->settings->get('GENERAL', 'admin_email');
 * ```
 *
 * @category Component
 * @package  Mitisk\Yii2Admin\components
 * @author   Mitisk <akimkinpit@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/mitisk/yii2-admin
 */
class SettingsComponent extends Component
{
    /**
     * Сохраняет значение настройки (создаёт запись, если её нет).
     *
     * @param string $modelName Ключ раздела (`GENERAL`, `ADMIN`) или FQCN модели.
     * @param string $key       Имя атрибута настройки.
     * @param mixed  $value     Значение. Приводится к строке при сохранении.
     * @param string $type      Тип: string|text|integer|float|boolean|json|file.
     *
     * @return bool Успех сохранения.
     */
    public function set(string $modelName, string $key, mixed $value, string $type = 'string'): bool
    {
        return Settings::setValue($modelName, $key, $value, $type);
    }

    /**
     * Возвращает значение настройки (с in-memory кешем в рамках запроса).
     *
     * @param string $modelName    Ключ раздела или FQCN модели.
     * @param string $key          Имя атрибута настройки.
     * @param mixed  $default      Значение по умолчанию, если настройка не найдена.
     * @param bool   $getOnlyValue true — скаляр; false — объект Settings.
     *
     * @return mixed Значение настройки, объект {@see Settings} или `$default`.
     */
    public function get(string $modelName, string $key, mixed $default = null, bool $getOnlyValue = true): mixed
    {
        return Settings::getValue($modelName, $key, $default, getOnlyValue: $getOnlyValue);
    }
}
