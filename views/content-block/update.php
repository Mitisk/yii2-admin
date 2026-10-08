<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\ContentBlockForm $form */
/** @var bool $modal */

$this->title = $this->params['pageHeaderText'] = $form->block->name;
$this->params['breadcrumbs'][] = ['label' => 'Текстовые блоки', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

echo $this->render('_form', ['form' => $form, 'modal' => $modal]);
