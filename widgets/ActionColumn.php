<?php

namespace Mitisk\Yii2Admin\widgets;

class ActionColumn extends \yii\grid\ActionColumn
{
    public $icons = [
        'eye-open' => '<div class="item eye"><i class="icon-eye"></i></div>',
        'pencil' => '<div class="item edit"><i class="icon-edit-3"></i></div>',
        'trash' => '<div class="item trash"><i class="icon-trash-2"></i></div>'
    ];
}
