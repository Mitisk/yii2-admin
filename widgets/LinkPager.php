<?php

namespace Mitisk\Yii2Admin\widgets;

class LinkPager extends \yii\widgets\LinkPager
{
    public $options = ['class' => 'wg-pagination'];
    public $prevPageLabel = '<i class="icon-chevron-left"></i>';

    public $nextPageLabel = '<i class="icon-chevron-right"></i>';
}