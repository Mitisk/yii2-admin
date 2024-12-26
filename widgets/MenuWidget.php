<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\Menu;
use Yii;
use yii\base\Widget;

class MenuWidget extends Widget
{

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $menuArray = [];
        $menu = Menu::find()->where(['alias' => 'admin'])->one();

        if($menu && $menu->data){
            $menuArray = json_decode($menu->data, true);
        }

        return $this->render('menu', ['menuArray' => $menuArray]);
    }
}