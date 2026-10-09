<?php

/** @title Обычная страница */
/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\Page $page */
/** @var string $content */

use Mitisk\Yii2Admin\widgets\AdminBar;
use yii\helpers\Html;

$this->params['breadcrumbs'] = Yii::$app->pages->breadcrumbs($page);
?>
<article class="page page-<?= Html::encode($page->template) ?>">
    <h1><?= AdminBar::editable($page, 'title') ?></h1>
    <?= AdminBar::editable($page, 'body', $content, ['tag' => 'div', 'type' => 'html', 'class' => 'page-body']) ?>
</article>
