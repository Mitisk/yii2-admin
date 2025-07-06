<?php
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $menuArray [] */
?>
<?php $this->registerCssFile('https://use.fontawesome.com/releases/v5.3.1/css/all.css') ?>
<?php $this->registerCssFile('/js/bootstrap-iconpicker/css/bootstrap-iconpicker.min.css') ?>


<div class="section-menu-left">
    <div class="box-logo">
        <a href="/admin/" id="site-logo-inner">
            <img class="" id="logo_header" alt="" src="/web/images/logo/logo.png" data-light="images/logo/logo.png" data-dark="images/logo/logo-dark.png" >
        </a>
        <div class="button-show-hide">
            <i class="icon-menu-left"></i>
        </div>
    </div>
    <div class="center">

        <div class="center-item">

            <ul class="menu-list">

                <?php foreach ($menuArray as $item): ?>
                    <li class="menu-item <?= ArrayHelper::getValue($item, 'children') ? 'has-children' : '' ?>">
                        <a href="<?= ArrayHelper::getValue($item, 'href') ? ArrayHelper::getValue($item, 'href') : 'javascript:void(0);' ?>"
                           title="<?= ArrayHelper::getValue($item, 'title') ? ArrayHelper::getValue($item, 'title') : ArrayHelper::getValue($item, 'text') ?>"
                           target="<?= ArrayHelper::getValue($item, 'target', '_self') ?>" class="menu-item-button">
                            <?php if (ArrayHelper::getValue($item, 'icon')): ?>
                                <div class="icon"><i class="<?= $item['icon'] ?>"></i></div>
                            <?php endif; ?>
                            <div class="text"><?= ArrayHelper::getValue($item, 'text') ?></div>
                        </a>
                        <?php if (ArrayHelper::getValue($item, 'children')): ?>
                            <ul class="sub-menu">
                                <?php foreach (ArrayHelper::getValue($item, 'children') as $subItem): ?>
                                    <li class="sub-menu-item <?= ArrayHelper::getValue($subItem, 'children') ? 'has-children' : '' ?>">
                                        <a href="<?= ArrayHelper::getValue($subItem, 'href') ? ArrayHelper::getValue($subItem, 'href') : 'javascript:void(0);' ?>"
                                           title="<?= ArrayHelper::getValue($subItem, 'title') ? ArrayHelper::getValue($subItem, 'title') : ArrayHelper::getValue($subItem, 'text') ?>"
                                           target="<?= ArrayHelper::getValue($subItem, 'target', '_self') ?>">
                                            <div class="text"><?= ArrayHelper::getValue($subItem, 'text') ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

            </ul>
        </div>
        <div class="center-item">
            <div class="center-heading">Настройки</div>
            <ul class="menu-list">
                <li class="menu-item">
                    <a href="/admin/settings/" class="">
                        <div class="icon"><i class="icon-settings"></i></div>
                        <div class="text">Основные</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="/admin/components/" class="">
                        <div class="icon"><i class="icon-database"></i></div>
                        <div class="text">Компоненты</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="/admin/menu/" class="menu-item-button">
                        <div class="icon"><i class="fas fa-bars"></i></div>
                        <div class="text">Меню</div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="center-item">
            <div class="center-heading">Connect us</div>
            <ul class="wg-social">
                <li>
                    <a href="#"><i class="icon-facebook"></i></a>
                </li>
                <li class="active">
                    <a href="#"><i class="icon-twitter"></i></a>
                </li>
                <li>
                    <a href="#"><i class="icon-linkedin"></i></a>
                </li>
                <li>
                    <a href="#"><i class="icon-instagram"></i></a>
                </li>
            </ul>
        </div>
    </div>
    <div class="bot text-center">
        <div class="wrap">
            <div class="mb-20">
                <img src="/web/images/menu-left/img-bot.png" alt="">
            </div>
            <div class="mb-20">
                <h6>Hi, how can we help?</h6>
                <div class="text">Contact us if you have any assistance, we will contact you as soon as possible</div>
            </div>
            <a href="#" class="tf-button w-full">Contact</a>
        </div>
    </div>
</div>
