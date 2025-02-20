<?php

namespace Mitisk\Yii2Admin\models;

use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%menu}}".
 *
 * @property int $id
 * @property string|null $name Название
 * @property string|null $alias Алиас
 * @property string|null $data Данные
 * @property bool $not_editable Нельзя удалить и редактировать название
 * @property int|null $ordering Сортировка
 */
class Menu extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName() : string
    {
        return '{{%menu}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['data'], 'string'],
            [['ordering', 'not_editable'], 'integer'],
            [['alias'], 'Mitisk\Yii2Admin\components\AliasValidator', 'skipOnEmpty' => false],
            [['alias'], 'unique'],
            [['name', 'alias'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'alias' => 'Алиас',
            'data' => 'Данные',
            'not_editable' => 'Нельзя удалить и редактировать название',
            'ordering' => 'Сортировка',
        ];
    }

    /**
     * Добавить в меню
     * @param string $alias Алиас меню
     * @param array $data Массив вида ['text' => 'Название', 'href' => 'URL', 'target' => '_blank', 'icon' => 'icon', 'title' => 'title']
     * @return bool|int
     * @throws \Exception
     */
    public static function addToMenu(string $alias, array $data) : bool|int
    {
        return self::updateMenu($alias, $data, true);
    }

    /**
     * Удалить из меню
     * @param string $alias Алиас меню
     * @param string $href Ссылка
     * @return bool|int
     * @throws \Exception
     */
    public static function removeFromMenu(string $alias, string $href) : bool|int
    {
        return self::updateMenu($alias, ['href' => $href], false);
    }

    /**
     * Обновить меню
     * @param string $alias Алиас меню
     * @param array $data Массив вида ['text' => 'Название', 'href' => 'URL', 'target' => '_blank', 'icon' => 'icon', 'title' => 'title']
     * @param bool $isAdd Добавить или удалить
     * @return bool|int
     * @throws \Exception
     */
    private static function updateMenu(string $alias, array $data, bool $isAdd) : bool|int
    {
        $menu = self::findOne(['alias' => $alias]);
        if ($menu) {
            $href = ArrayHelper::getValue($data, 'href');
            if ($href) {
                $menuData = json_decode($menu->data, true);
                if ($isAdd) {
                    // Проверяем на наличие в меню
                    foreach ($menuData as $item) {
                        if (ArrayHelper::getValue($item, 'href') == $href) {
                            return true;  // Элемент уже существует, ничего не делаем
                        }
                    }
                    // Добавляем элемент в меню
                    $menuData[] = $data;
                } else {
                    // Удаляем элемент из меню
                    $menuData = array_filter($menuData, function ($item) use ($href) {
                        return ArrayHelper::getValue($item, 'href') !== $href; // Условия для удаления
                    });
                }
                return self::updateAll(['data' => json_encode(array_values($menuData))], ['id' => $menu->id]);
            }
        }
        return false;
    }
}
