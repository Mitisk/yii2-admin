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

$this->registerJs("
    window.adminConfig = {
        urls: {
            addWidget: '" . \yii\helpers\Url::to(['/admin/ajax-widget/add']) . "',
            updateWidgetOrder: '" . \yii\helpers\Url::to(['/admin/ajax-widget/update-order']) . "',
            hideWidget: '" . \yii\helpers\Url::to(['/admin/ajax-widget/hide']) . "',
            componentPopup: '" . \yii\helpers\Url::to(['/admin/ajax-widget/component-popup']) . "',
            saveComponent: '" . \yii\helpers\Url::to(['/admin/ajax-widget/save-component']) . "',
            getNote: '" . \yii\helpers\Url::to(['/admin/ajax-note/get']) . "',
            saveNote: '" . \yii\helpers\Url::to(['/admin/ajax-note/save']) . "',
            saveApi: '" . \yii\helpers\Url::to(['/admin/settings/save-api']) . "',
            updateSectionName: '" . \yii\helpers\Url::to(['/admin/settings/update-section-name']) . "',
            uploadAvatar: '" . \yii\helpers\Url::to(['/admin/ajax/upload-avatar']) . "',
            deleteAvatar: '" . \yii\helpers\Url::to(['/admin/ajax/delete-avatar']) . "',
            updateAttribute: '" . \yii\helpers\Url::to(['update-attribute']) . "'
        }
    };
", \yii\web\View::POS_HEAD);

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
                                <a href="<?= \yii\helpers\Url::to(['/admin/default/index']) ?>">
                                    <?php
                                    $logo = \Yii::$app->settings->get('ADMIN', 'logo');
                                    if (empty($logo)) {
                                        $logo = \Mitisk\Yii2Admin\assets\AppAsset::register($this)->baseUrl . '/img/logo.png';
                                    }
                                    ?>
                                    <img id="logo_header_mobile" alt="Logo" src="<?= $logo ?>">
                                </a>
                                <div class="button-show-hide">
                                    <i class="icon-menu-left"></i>
                                </div>
                                <?php if (Yii::$app->session->has('impersonator_id')): ?>

                                    <div class="block-warning type-main">
                                        <i class="icon-alert-octagon"></i>
                                        <div class="body-title-2" style="display: flex;justify-content: center;align-items: center;">
                                            Вы просматриваете сайт от имени пользователя <?= Yii::$app->user->identity->username ?>.
                                            <?= \yii\helpers\Html::a(
                                                'Вернуться в админку',
                                                ['/admin/user/stop-impersonate'],
                                                ['class' => 'btn btn-sm block-pending', 'style' => 'float: inline-end;margin-left: 10px;']
                                            ) ?>
                                        </div>
                                    </div>

                                <?php endif; ?>
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
                                            'homeLink' => ['label' => 'Главная', 'url' => ['/admin/default/index']],
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