<?php

namespace Mitisk\Yii2Admin\widgets;

class ActionColumn extends \yii\grid\ActionColumn
{
    public $icons = [
        'eye-open' => '<span class="item eye"><i class="icon-eye"></i></span>',
        'pencil' => '<span class="item edit"><i class="icon-edit-3"></i></span>',
        'trash' => '<span class="item trash"><i class="icon-trash-2"></i></span>'
    ];
}
