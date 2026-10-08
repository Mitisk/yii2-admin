<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\ContentBlockForm $form */

$this->title = $this->params['pageHeaderText'] = 'Новый блок: ' . $form->getBlockType()->label();
$this->params['breadcrumbs'][] = ['label' => 'Текстовые блоки', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

echo $this->render('_form', ['form' => $form, 'modal' => false]);
