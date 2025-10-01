<?php
use yii\helpers\Html;

/* @var $model \Mitisk\Yii2Admin\models\AdminControllerMap */

$this->title = 'Создать сопоставление';
$this->params['breadcrumbs'][] = ['label' => 'Компоненты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="admin-controller-map-create wg-order-detail">
    <div class="left flex-grow">
        <div class="wg-box">
            <div class="row">
                <div class="col-12 mb-20">
                    <div>
                        <?= $this->render('_form_map', compact('model')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="right">
        <?= $this->render('_map_examples') ?>
    </div>
</div>
