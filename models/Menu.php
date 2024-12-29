<?php

namespace Mitisk\Yii2Admin\models;

use Yii;
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
    public static function tableName()
    {
        return '{{%menu}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
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
    public function attributeLabels()
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
     * @return bool
     */
    public static function addToMenu($alias, $data)
    {
        $menu = self::findOne(['alias' => $alias]);
        if ($menu) {
            $href = ArrayHelper::getValue($data, 'href');
            if($href) {
                $menuData = json_decode($menu->data, true);
                //Проверяем на наличие в меню
                foreach ($menuData as $item) {
                    if(ArrayHelper::getValue($item, 'href') == $href) {
                        return true;
                    }
                }

                return self::updateAll(['data' => json_encode(ArrayHelper::merge($menuData, [$data]))], ['id' => $menu->id]);
            }
        }
        return false;
    }
}
