<?php
/**
 * @var yii\web\View $this
 * @var Mitisk\Yii2Admin\models\SeoRule $model
 */

use yii\helpers\Html;

$this->title = 'Редактирование: ' . Html::encode($model->pattern);
$this->params['breadcrumbs'][] = ['label' => 'SEO-правила', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Редактирование';
?>

<?= $this->render('_form', ['model' => $model]) ?>
