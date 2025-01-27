<?php
use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */

$this->title = $model->getName();
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="col-12 mb-20">
    <div class="wg-box">
        <div class="row">
            <div class="col-12 mb-20">
                <div>
                    <?= DetailView::widget([
                        'model' => $model->getModel(),
                        'attributes' => $model->getDetailViewHelper()
                    ])
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
