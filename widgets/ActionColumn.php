<?php

namespace Mitisk\Yii2Admin\widgets;

use yii\helpers\Html;

class ActionColumn extends \yii\grid\ActionColumn
{
    public $headerOptions = ['class' => 'actions-col'];

    public $contentOptions = ['class' => 'actions-col'];

    public $header = 'Действия';

    public $icons = [
        'eye-open' => '<i class="icon-eye"></i>',
        'pencil' => '<i class="icon-edit-3"></i>',
        'trash' => '<i class="icon-trash-2"></i>',
    ];

    /**
     * @inheritdoc
     */
    protected function renderDataCellContent(
        $model,
        $key,
        $index
    ): string {
        return Html::tag(
            'div',
            parent::renderDataCellContent($model, $key, $index),
            ['class' => 'actions-group']
        );
    }

    /**
     * @inheritdoc
     */
    protected function initDefaultButtons(): void
    {
        $this->initDefaultButton('view', 'eye-open', [
            'class' => 'btn-action view',
            'title' => 'Просмотр',
        ]);
        $this->initDefaultButton('update', 'pencil', [
            'class' => 'btn-action edit',
            'title' => 'Редактировать',
        ]);
        $this->initDefaultButton('delete', 'trash', [
            'class' => 'btn-action delete',
            'title' => 'Удалить',
            'data-confirm' => \Yii::t(
                'yii',
                'Are you sure you want to delete this item?'
            ),
            'data-method' => 'post',
        ]);
    }
}
