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
                                <!--a href="' . \yii\helpers\Url::to($model->getUrls('update')) . '" class="item edit">
                                    <i class="icon-edit-3"></i>
                                </a-->
                            </div>
                            </li>',
                    ])
                    ?>

                </div>

            </div>
        </div>

        <div class="bot">
            <div class="list-box-value mb-10">
                <?php if ($model->canCreate()) : ?>
                <div>
                    <?= Html::a("Редактировать", $model->getUrls('update'), [
                        'class' => 'tf-button w208'
                    ]) ?>
                </div>
                <?php endif; ?>
                <?php if ($model->canDelete()) : ?>
                <div>
                    <?= Html::a(Yii::t('rbac', 'Delete'), ['delete', 'id' => $model->getModel()->id], [
                        'class' => 'tf-button tf-button-danger w208',
                        'data-confirm' => Yii::t('rbac', 'Are you sure to delete this item?'),
                        'data-method' => 'post',
                    ]) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
