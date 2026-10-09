<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\PageForm $form */
/** @var bool $modal */

$this->title = $this->params['pageHeaderText'] = $form->page->title;
$this->params['breadcrumbs'][] = ['label' => 'Страницы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

echo $this->render('_form', ['form' => $form, 'modal' => $modal]);
