<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\core\models\AdminModel */

$this->title = $model->getName();
$this->params['breadcrumbs'][] = $this->title;
?>