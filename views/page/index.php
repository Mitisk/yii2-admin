<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\PageSearch $searchModel */
/** @var list<array{item: Mitisk\Yii2Admin\models\Page, depth: int}> $rows */
/** @var bool $canManage */
/** @var bool $canEdit */

use Mitisk\Yii2Admin\assets\PageAsset;
use Mitisk\Yii2Admin\enums\PageStatus;
use yii\helpers\Html;

PageAsset::register($this);
$this->title = $this->params['pageHeaderText'] = 'Страницы';
$this->params['breadcrumbs'][] = 'Контент';
$this->params['breadcrumbs'][] = $this->title;
$templates = Yii::$app->pages->templates();
$this->registerCss('.page-row .text-tiny code { background:#f1f5f9; padding:2px 6px; border-radius:6px; }');
?>
<div class="wg-box mb-20">
    <form method="get" class="flex items-center gap10 flex-wrap">
        <input type="text" name="q" value="<?= Html::encode($searchModel->q) ?>" placeholder="Заголовок или адрес" style="max-width:260px">
        <fieldset class="select" style="width:180px"><?= Html::dropDownList('status', $searchModel->status, ['' => 'Любой статус'] + PageStatus::options(), ['class' => 'tom-select']) ?></fieldset>
        <fieldset class="select" style="width:220px"><?= Html::dropDownList('template', $searchModel->template, ['' => 'Любой шаблон'] + $templates, ['class' => 'tom-select']) ?></fieldset>
        <button type="submit" class="tf-button style-2">Найти</button>
        <?php if ($canManage): ?>
            <span class="flex-grow"></span>
            <?= Html::a('<i class="icon-plus"></i> Новая страница', ['create'], ['class' => 'tf-button style-1']) ?>
        <?php endif; ?>
    </form>
</div>

<div class="wg-box">
    <?php if ($rows === []): ?>
        <div class="body-text">Страниц пока нет.</div>
    <?php else: ?>
    <div class="wg-table table-all-attribute">
        <ul class="table-title flex gap20 mb-14">
            <li class="flex-grow"><div class="body-title">Страница</div></li>
            <li style="width:130px"><div class="body-title">Статус</div></li>
            <li style="width:150px"><div class="body-title">Шаблон</div></li>
            <li style="width:150px"><div class="body-title">Изменена</div></li>
            <li style="width:200px"><div class="body-title">Действия</div></li>
        </ul>
        <ul class="flex flex-column">
        <?php foreach ($rows as $row): $p = $row['item']; $status = $p->getStatus(); $live = $p->isLive(); ?>
            <li class="attribute-item flex items-center gap20 page-row" data-page-id="<?= $p->id ?>">
                <div class="flex-grow" style="padding-left:<?= $row['depth'] * 24 ?>px">
                    <div class="body-title-2"><?= Html::a(Html::encode($p->title), ['update', 'id' => $p->id]) ?></div>
                    <div class="text-tiny"><code>/<?= Html::encode($p->path) ?></code></div>
                </div>
                <div style="width:130px">
                    <span class="badge <?= $status->badgeClass() ?>"><?= $status->label() ?></span>
                    <?php if ($status === PageStatus::Published && !$live): ?><div class="text-tiny">с <?= date('d.m H:i', (int)$p->published_at) ?></div><?php endif; ?>
                </div>
                <div style="width:150px" class="body-text"><?= Html::encode($templates[$p->template] ?? $p->template) ?></div>
                <div style="width:150px">
                    <div class="body-text"><?= Yii::$app->formatter->asDatetime($p->updated_at, 'dd.MM.yyyy HH:mm') ?></div>
                    <?php if ($p->updater): ?><div class="text-tiny"><?= Html::encode($p->updater->name ?: $p->updater->username) ?></div><?php endif; ?>
                </div>
                <div class="list-icon-function" style="width:200px">
                    <?php // Ссылка с токеном — право editContent; остальным черновик откроется по сессии админки ?>
                    <?= Html::a('<i class="icon-external-link"></i>', $live || !$canEdit ? Yii::$app->pages->url($p) : Yii::$app->pages->previewUrl($p), ['class' => 'item', 'target' => '_blank', 'title' => 'Открыть на сайте']) ?>
                    <?php if ($canEdit): ?>
                        <?= Html::a('<i class="icon-edit-3"></i>', ['update', 'id' => $p->id], ['class' => 'item edit', 'title' => 'Редактировать']) ?>
                        <?= $status === PageStatus::Published
                            ? Html::a('<i class="icon-eye-off"></i>', ['unpublish', 'id' => $p->id], ['class' => 'item', 'title' => 'Снять с публикации', 'data-method' => 'post'])
                            : Html::a('<i class="icon-check"></i>', ['publish', 'id' => $p->id], ['class' => 'item', 'title' => 'Опубликовать', 'data-method' => 'post']) ?>
                    <?php endif; ?>
                    <?php if ($canManage): ?>
                        <?= Html::a('<i class="icon-copy"></i>', ['duplicate', 'id' => $p->id], ['class' => 'item', 'title' => 'Дублировать', 'data-method' => 'post']) ?>
                        <?= Html::a('<i class="icon-plus"></i>', ['create', 'parent' => $p->id], ['class' => 'item', 'title' => 'Создать вложенную']) ?>
                        <?= Html::a('<i class="icon-trash-2"></i>', ['delete', 'id' => $p->id], ['class' => 'item text-delete', 'title' => 'Удалить', 'data-method' => 'post', 'data-confirm' => 'Удалить страницу «' . $p->title . '» и все вложенные в неё?']) ?>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
