<?php
use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */

$this->title = $model->getName();
$this->params['breadcrumbs'][] = ['label' => $model->getComponentName(), 'url' => ['index', 'page-alias' => $model->component->alias]];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="col-12 mb-20">
    <div class="wg-box">

        <div class="flex items-center justify-between gap10 flex-wrap">
            <div class="wg-filter flex-grow">
            </div>
            <?= Html::a('<div class="item trash"><i class="icon-trash-2"></i></div>', ['delete', 'id' => $model->getModel()->id], [
                'class' => 'tf-button style-1',
                'data' => [
                    'confirm' => 'Вы уверены, что хотите удалить этот элемент?',
                    'method' => 'post',
                ],
            ]) ?>
        </div>

        <div class="row">
            <div class="col-12 mb-20">
                <div class="wg-table table-all-attribute">

                    <?= DetailView::widget([
                        'model' => $model->getModel(),
                        'attributes' => $model->getDetailView(),
                        'options' => [
                            'tag' => 'ul',
                            'class' => 'flex flex-column'
                        ],
                        'template' => '<li class="attribute-item flex items-center justify-between gap20">
                            <div class="body-title" {captionOptions}>{label}</div>
                            <div class="body-text" {contentOptions}>{value}</div>
                            <div class="justify-content-end list-icon-function">
                                <a href="' . \yii\helpers\Url::to($model->getUrls('update')) . '" class="item edit">
                                    <i class="icon-edit-3"></i>
                                </a>
                            </div>
                            </li>',
                    ])
                    ?>

                </div>

            </div>
        </div>
    </div>
</div>
