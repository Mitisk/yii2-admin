<?php

use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = $this->params['pageHeaderText'] = 'Шаблоны писем';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wg-box">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
        </div>

        <?= Html::a('<i class="icon-plus"></i> Создать шаблон', ['create'], ['class' => 'tf-button style-1']) ?>
        <?= Html::a('<i class="fa-light fa-table-layout"></i> Макет', ['layout'], ['class' => 'tf-button style-1']) ?>
        <?= Html::a("<i class=\"icon-settings\"></i>", ['/admin/settings/', 'modelName' => "Mitisk\Yii2Admin\models\MailTemplate"], ['class' => 'tf-button']) ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            'slug',
            'name',
            'subject',
            'active:boolean',
            [
                'attribute' => 'created_at',
                'filter' => false,
                'format' => ['datetime', 'php:d.m.Y H:i:s'],
            ],
            [
                'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
                'template' => '{update} {delete}',
            ],
        ],
    ]); ?>
</div>