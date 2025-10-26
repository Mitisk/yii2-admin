<?php

namespace Mitisk\Yii2Admin\widgets;

use yii\base\Widget;

final class IndexCenterWidget extends Widget
{
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        return $this->render('index/center');
    }
}
