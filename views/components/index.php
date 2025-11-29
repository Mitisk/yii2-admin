<?php

use Mitisk\Yii2Admin\widgets\GridView;
use yii\helpers\Html;
use Mitisk\Yii2Admin\models\AdminControllerMap;
use yii\helpers\Url;

/* @var $models \Mitisk\Yii2Admin\models\AdminModel[] */
/* @var $AdminControllerMapProvider AdminControllerMap[] */
/* @var $this yii\web\View */

$this->title = 'Компоненты';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wg-box mb-30">
    <div class="wg-table table-all-attribute">
        <ul class="table-title flex gap20 mb-14">
            <li>
                <div class="body-title">Название</div>
            </li>
            <li>
                <div class="body-title">Таблица</div>
            </li>
            <li>
                <div class="body-title"></div>
            </li>
        </ul>
        <ul class="flex flex-column">

            <?php foreach ($models as $model): ?>
                <li class="attribute-item flex items-center justify-between gap20">
                    <div class="name">
                        <a href="#" class="body-title-2"><?= $model->name ?>
                            <?php if (!$model->alias) : ?>
                            <div class="block-available" style="float: right">NEW</div>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="body-text"><?= $model->table_name ?></div>
                    <div class="list-icon-function">
                        <a href="/admin/components/update?id=<?= $model->id ?>" class="item edit">
                            <i class="icon-edit-3"></i>
                        </a>
                        <a href="/admin/components/delete?id=<?= $model->id ?>" class="item trash">
                            <i class="icon-trash-2"></i>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>

        </ul>
    </div>
</div>

<div class="flex items-center flex-wrap justify-between gap20 mb-27">
    <h3>Controller MAP</h3>
</div>
<div class="wg-box">
    <?php $AdminControllerMapModel = new AdminControllerMap(); ?>
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
            <form class="form-search">
                <fieldset class="name">
                    <input type="text" placeholder="Поиск..." class="" name="<?= $AdminControllerMapModel->formName() ?>[search]" tabindex="2" aria-required="true"
                           value="<?= \yii\helpers\ArrayHelper::getValue(Yii::$app->request->get(), $AdminControllerMapModel->formName() . '.search') ?>">
                </fieldset>
                <div class="button-submit">
                    <button class="" type="submit"><i class="icon-search"></i></button>
                </div>
            </form>
        </div>
        <?= Html::a("<i class=\"icon-plus\"></i> Добавить сопоставление", ['create-map'], ['class' => 'tf-button style-1']) ?>
    </div>
    <?php
    echo GridView::widget([
        'dataProvider' => $AdminControllerMapProvider,
        'filterModel' => $AdminControllerMapModel,
        'tableOptions' => ['class' => 'wg-table table-all-roles'],
        'rowOptions' => ['class' => "roles-item"],
        'contentOptions' => ['class' => "body-text"],
        'columns' => [
            'controller_id',
            'class',
            [
                'attribute' => 'created_at',
                'filter' => false,
                'format' => ['datetime', 'php:d.m.Y H:i:s'],
            ],
            [
                'attribute' => 'updated_at',
                'format' => ['datetime', 'php:d.m.Y H:i:s'],
                'filter' => false,
            ],
            [
                'attribute' => 'enabled',
                'format' => 'html',
                'value' => function ($data) {
                    return $data->enabled
                        ? '<div class="block-available">да</div>'
                        : '<div class="block-not-available">нет</div>';
                }
            ],
            [
                'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
                'template' => '{update} {delete}',
                'urlCreator' => function (string $action, $model, $key, int $index, $column) {
                    if ($action === 'update') {
                        return Url::to(['/admin/components/update-map', 'id' => $model->id]);
                    }
                    if ($action === 'delete') {
                        return Url::to(['/admin/components/delete-map', 'id' => $model->id]);
                    }
                    return '#';
                },
                'buttonOptions' => ['class' => '']
            ],
        ],
    ]);
    ?>
</div>


