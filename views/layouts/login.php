<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use Mitisk\Yii2Admin\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
?>
<?php $this->beginPage() ?>
    <!DOCTYPE html>
    <!--[if IE 8 ]><html class="ie" xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US"> <![endif]-->
    <!--[if (gte IE 9)|!(IE)]><!-->
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?= Yii::$app->language ?>" lang="<?= Yii::$app->language ?>">
    <!--<![endif]-->
    <head>
        <meta charset="utf-8">
        <title><?= Html::encode($this->title) ?></title>
        <?php $this->head() ?>

    </head>

    <body class="body">
    <?php $this->beginBody() ?>

    <!-- #wrapper -->
    <div id="wrapper">
        <!-- #page -->
        <div id="page" class="">

            <?php if (!empty($this->params['breadcrumbs'])): ?>
                <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
            <?php endif ?>


            <?= Alert::widget() ?>

            <?= $content ?>

        </div>
        <!-- /#page -->
    </div>
    <!-- /#wrapper -->

    <?php $this->endBody() ?>
    </body>
    </html>
<?php $this->endPage() ?>