<?php
/**
 * @var yii\web\View $this
 * @var Mitisk\Yii2Admin\models\SeoRule $model
 */

$this->title = 'Новое SEO-правило';
$this->params['breadcrumbs'][] = ['label' => 'SEO-правила', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<?= $this->render('_form', ['model' => $model]) ?>
