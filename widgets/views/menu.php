<?php

use Mitisk\Yii2Admin\components\MenuHelper;

/* @var $this yii\web\View */
/* @var $menuArray [] */
?>
<div class="section-menu-left">
    <div class="box-logo">
        <a href="/admin/" id="site-logo-inner">
            <img id="logo_header" alt="" src="<?= \Yii::$app->settings->get('ADMIN', 'logo'); ?>">
        </a>
        <div class="button-show-hide"><i class="icon-menu-left"></i></div>
    </div>

    <div class="center">
        <div class="center-item">
            <ul class="menu-list">
                <?php foreach ($menuArray as $item): ?>
                    <?php
                    $hasChildren = !empty($item['children']);
                    $isActive = !empty($item['_active']);
                    $href = $item['href'] ?? 'javascript:void(0);';
                    $title = $item['title'] ?? ($item['text'] ?? '');
                    $target = $item['target'] ?? '_self';
                    $icon = $item['icon'] ?? null;
                    ?>
                    <li class="menu-item <?= $hasChildren ? 'has-children' : '' ?> <?= $isActive ? 'active' : '' ?>">
                        <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
                           title="<?= htmlspecialchars($title, ENT_QUOTES) ?>"
                           target="<?= htmlspecialchars($target, ENT_QUOTES) ?>"
                           class="menu-item-button">
                            <?php if ($icon): ?>
                                <div class="icon"><i class="<?= htmlspecialchars($icon, ENT_QUOTES) ?>"></i></div>
                            <?php endif; ?>
                            <div class="text"><?= htmlspecialchars($item['text'] ?? '', ENT_QUOTES) ?></div>
                        </a>

                        <?php if ($hasChildren): ?>
                            <ul class="sub-menu">
                                <?php foreach ($item['children'] as $sub): ?>
                                    <?php
                                    $subActive = !empty($sub['_active']);
                                    $subHasChildren = !empty($sub['children']);
                                    $subHref = $sub['href'] ?? 'javascript:void(0);';
                                    $subTitle = $sub['title'] ?? ($sub['text'] ?? '');
                                    $subTarget = $sub['target'] ?? '_self';
                                    ?>
                                    <li class="sub-menu-item <?= $subHasChildren ? 'has-children' : '' ?> <?= $subActive ? 'active' : '' ?>">
                                        <a href="<?= htmlspecialchars($subHref, ENT_QUOTES) ?>"
                                           title="<?= htmlspecialchars($subTitle, ENT_QUOTES) ?>"
                                           target="<?= htmlspecialchars($subTarget, ENT_QUOTES) ?>">
                                            <div class="text"><?= htmlspecialchars($sub['text'] ?? '', ENT_QUOTES) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (Yii::$app->user->can('admin')): ?>
            <div class="center-item">
                <div class="center-heading">Настройки</div>
                <ul class="menu-list">
                    <li class="menu-item <?= MenuHelper::build([
                        ['href' => '/admin/settings/', 'text'=>'Основные']
                    ])[0]['_active'] ? 'active' : '' ?>">
                        <a href="/admin/settings/">
                            <div class="icon"><i class="icon-settings"></i></div>
                            <div class="text">Основные</div>
                        </a>
                    </li>

                    <?php if (Yii::$app->user->can('superAdmin')): ?>
                        <li class="menu-item <?= MenuHelper::build([
                            ['href' => '/admin/components/', 'text'=>'Компоненты']
                        ])[0]['_active'] ? 'active' : '' ?>">
                            <a href="/admin/components/">
                                <div class="icon"><i class="icon-database"></i></div>
                                <div class="text">Компоненты</div>
                            </a>
                        </li>
                        <li class="menu-item <?= MenuHelper::build([
                            ['href' => '/admin/menu/', 'text'=>'Меню']
                        ])[0]['_active'] ? 'active' : '' ?>">
                            <a href="/admin/menu/" class="menu-item-button">
                                <div class="icon"><i class="fas fa-bars"></i></div>
                                <div class="text">Меню</div>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
