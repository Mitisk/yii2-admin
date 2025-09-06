<?php

/** @var yii\web\View $this */
/** @var string $content */

use Mitisk\Yii2Admin\assets\AppAsset;
use Mitisk\Yii2Admin\widgets\Alert;
use Mitisk\Yii2Admin\widgets\Breadcrumbs;
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

            <!-- layout-wrap -->
            <div class="layout-wrap">
                <!-- preload -->
                <div id="preload" class="preload-container">
                    <div class="preloading">
                        <span></span>
                    </div>
                </div>
                <!-- /preload -->
                <!-- section-menu-left -->
                <?= \Mitisk\Yii2Admin\widgets\MenuWidget::widget() ?>
                <!-- /section-menu-left -->
                <!-- section-content-right -->
                <div class="section-content-right">
                    <!-- header-dashboard -->
                    <div class="header-dashboard">
                        <div class="wrap">
                            <div class="header-left">
                                <a href="/admin/">
                                    <img class="" id="logo_header_mobile" alt="" src="/web/images/logo/logo.png"
                                         data-light="/web/images/logo/logo.png"
                                         data-dark="/web/images/logo/logo-dark.png" data-width="154px" data-height="52px" data-retina="/web/images/logo/logo@2x.png">
                                </a>
                                <div class="button-show-hide">
                                    <i class="icon-menu-left"></i>
                                </div>
                            </div>
                            <div class="header-grid">

                                <?= \Mitisk\Yii2Admin\widgets\UserPersonalMenu::widget() ?>

                            </div>
                        </div>
                    </div>
                    <!-- /header-dashboard -->

                    <!-- main-content -->
                    <div class="main-content">
                        <!-- main-content-wrap -->
                        <div class="main-content-inner">
                            <!-- main-content-wrap -->
                            <div class="main-content-wrap">

                                <div class="flex items-center flex-wrap justify-between gap20 mb-27">
                                    <h3><?= $this->title ?></h3>

                                    <?php if (!empty($this->params['breadcrumbs'])): ?>
                                        <?= Breadcrumbs::widget([
                                            'homeLink' => ['label' => 'Главная', 'url' => '/admin/'],
                                            'links' => $this->params['breadcrumbs']
                                        ]) ?>
                                    <?php endif ?>
                                </div>

                                <?= Alert::widget() ?>

                                <?= $content ?>

                            </div>
                            <!-- /main-content-wrap -->
                        </div>
                        <!-- /main-content-wrap -->
                        <!-- bottom-page -->
                        <div class="bottom-page">
                            <div class="body-text">
                                <span id="currentDateTime"><?= date('d.m.Y H:i')?></span> <a href="https://keypage.ru/"><i class="icon-heart"></i> KeyPage.ru</a>
                            </div>
                        </div>
                        <!-- /bottom-page -->
                    </div>
                    <!-- /main-content -->

                </div>
                <!-- /section-content-right -->
            </div>
            <!-- /layout-wrap -->
        </div>
        <!-- /#page -->
    </div>
    <!-- /#wrapper -->

    <?php $this->endBody() ?>
    </body>
    </html>
<?php $this->endPage() ?>