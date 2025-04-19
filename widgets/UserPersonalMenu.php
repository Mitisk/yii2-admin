<?php

namespace Mitisk\Yii2Admin\widgets;

use yii\base\Widget;

class UserPersonalMenu extends Widget
{

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        return $this->render('user_personal_menu');
    }
}
