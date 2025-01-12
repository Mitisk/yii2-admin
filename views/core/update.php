<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/* @var $formTemplate string */

$this->title = 'Редактировать: ' . $model->getName();
$this->params['breadcrumbs'][] = ['label' => $model->getComponentName(), 'url' => ['index', 'page-alias' => $model->component->alias]];
$this->params['breadcrumbs'][] = ['label' => $model->getName(), 'url' => ['view', 'id' => $model->getModel()->id, 'page-alias' => $model->component->alias]];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<div class="col-12 mb-20">
    <div class="wg-box">
        <div class="row">
            <div class="col-12 mb-20">
                <div>
                    <?= $this->render($formTemplate, ['model' => $model]) ?>
                </div>
            </div>
        </div>
    </div>
</div>