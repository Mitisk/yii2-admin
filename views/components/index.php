<?php
/* @var $models \Mitisk\Yii2Admin\models\AdminModel[] */
/* @var $this yii\web\View */

$this->title = 'Компоненты';
$this->params['breadcrumbs'][] = $this->title;
?>


<div class="wg-box">
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
                        <a href="add-attributes.html" class="body-title-2"><?= $model->name ?></a>
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