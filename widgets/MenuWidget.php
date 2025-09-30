<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\Menu;
use Yii;
use yii\base\Widget;
use yii\base\Event;
use Mitisk\Yii2Admin\components\MenuHelper;

/**
 * MenuWidget
 * Для добавления пунктов меню в виджет:
 * Yii::$app->on(MenuWidget::EVENT_BEFORE_RENDER, function ($event) {
 * // Здесь вы можете добавить свои пункты в массив $event->menuArray
 * $event->menuArray[] = [
 * 'label' => 'Новый пункт меню',
 * 'href' => '/new-item',
 * 'icon' => 'new-icon'
 * ];
 * });
 */
class MenuWidget extends Widget
{
    const EVENT_BEFORE_RENDER = 'beforeRenderMenu';

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $menuArray = [];

        /** @var Menu $menu */
        $menu = Menu::find()->where(['alias' => 'admin'])->one();

        // Проверяем, действительно ли меню найдено и содержит ли данные
        if ($menu !== null) {
            // Декодируем JSON-данные в ассоциативный массив
            $decodedData = json_decode($menu->data, true);

            // Проверяем, не произошла ли ошибка при декодировании JSON
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                $menuArray = $decodedData; // Если данных нет или произошла ошибка, массив останется пустым
            } else {
                Yii::warning("Menu data is not a valid JSON or is not an array: " . json_last_error_msg(), __METHOD__);
            }
        } else {
            Yii::warning("Menu with alias 'admin' not found.", __METHOD__);
        }

        // Создаем событие перед рендерингом меню
        $event = new MenuEvent(['menuArray' => &$menuArray]);
        $this->trigger(self::EVENT_BEFORE_RENDER, $event);

        // Применяем перед рендером
        $menuArray = MenuHelper::build($menuArray);

        return $this->render('menu', ['menuArray' => $menuArray]);
    }
}
class MenuEvent extends Event
{
    /**
     * @var array
     */
    public array $menuArray;
}
