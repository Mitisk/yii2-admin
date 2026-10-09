<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\PageSearch $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var bool $canManage */
/** @var bool $canEdit */

use Mitisk\Yii2Admin\enums\PageStatus;
use Mitisk\Yii2Admin\models\Page;
use Mitisk\Yii2Admin\widgets\ActionColumn;
use Mitisk\Yii2Admin\widgets\GridView;
use yii\helpers\Html;

$this->title = $this->params['pageHeaderText'] = 'Страницы';
$this->params['breadcrumbs'][] = 'Контент';
$this->params['breadcrumbs'][] = $this->title;

$pages = Yii::$app->pages;
$templates = $pages->templates();
$depths = $searchModel->depths;
$formName = $searchModel->formName();

// Отступ и уголок вложенной страницы в дереве
$this->registerCss(<<<CSS
.page-tree { display:flex; align-items:flex-start; gap:8px; }
.page-tree-mark { color:#cbd5e1; font-size:16px; line-height:20px; flex-shrink:0; }
.page-tree-path { color:#94a3b8; }
.modern-table a.badge { color:#fff; text-decoration:none; }
CSS);
?>
<div class="wg-box mb-20">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
            <form class="form-search" method="get">
                <fieldset class="name">
                    <input type="text" placeholder="Поиск по заголовку и адресу..." name="<?= $formName ?>[q]" value="<?= Html::encode($searchModel->q) ?>">
                </fieldset>
                <?php // Поиск не сбрасывает фильтры колонок ?>
                <?php foreach (['status', 'template'] as $attr): ?>
                    <?php if ((string)$searchModel->$attr !== ''): ?>
                        <?= Html::hiddenInput($formName . '[' . $attr . ']', $searchModel->$attr) ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="button-submit">
                    <button type="submit"><i class="icon-search"></i></button>
                </div>
            </form>
            <button type="button" class="tf-button js-toggle-filters" title="Фильтры по колонкам">
                <i class="icon-sliders"></i>
            </button>
        </div>

        <?php if ($canManage): ?>
            <?= Html::a('<i class="icon-plus"></i> Новая страница', ['create'], ['class' => 'tf-button style-1 w208']) ?>
        <?php endif; ?>
    </div>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'emptyText' => $searchModel->isFiltered() ? 'Ничего не найдено.' : 'Страниц пока нет.',
    'columns' => [
        [
            'label' => 'Страница',
            'format' => 'raw',
            'value' => static function (Page $p) use ($depths): string {
                $depth = $depths[(int)$p->id] ?? 0;
                $text = Html::tag('div', Html::a(Html::encode($p->title), ['update', 'id' => $p->id], ['class' => 'body-title-2']))
                    . Html::tag('div', '/' . Html::encode($p->path), ['class' => 'text-tiny page-tree-path']);
                $mark = $depth > 0 ? Html::tag('span', '└', ['class' => 'page-tree-mark']) : '';
                return Html::tag('div', $mark . Html::tag('div', $text), [
                    'class' => 'page-tree',
                    'style' => $depth > 1 ? 'padding-left:' . (($depth - 1) * 24) . 'px' : null,
                ]);
            },
        ],
        [
            'attribute' => 'status',
            'label' => 'Статус',
            'format' => 'raw',
            'filter' => PageStatus::options(),
            'headerOptions' => ['style' => 'width:190px'],
            'value' => static function (Page $p) use ($canEdit): string {
                $status = $p->getStatus();
                $live = $p->isLive();
                $scheduled = $status === PageStatus::Published && !$live;
                $label = $scheduled ? 'Публикация ' . date('d.m H:i', (int)$p->published_at) : $status->label();
                $class = 'badge ' . ($scheduled ? 'bg-info' : $status->badgeClass());
                if (!$canEdit) {
                    return Html::tag('span', $label, ['class' => $class]);
                }
                // Как у тумблеров в других списках: клик по бейджу меняет статус
                return Html::a($label, [$live ? 'unpublish' : 'publish', 'id' => $p->id], [
                    'class' => $class,
                    'title' => $live ? 'Нажмите, чтобы снять с публикации' : 'Нажмите, чтобы опубликовать сейчас',
                    'data-method' => 'post',
                    'data-confirm' => $live ? 'Снять страницу «' . $p->title . '» с публикации?' : null,
                ]);
            },
        ],
        [
            'attribute' => 'template',
            'label' => 'Шаблон',
            'filter' => $templates,
            'value' => static fn(Page $p): string => $templates[$p->template] ?? $p->template,
        ],
        [
            'attribute' => 'updated_at',
            'label' => 'Изменена',
            'filter' => false,
            'format' => 'raw',
            'value' => static fn(Page $p): string => Html::tag('div', Yii::$app->formatter->asDatetime($p->updated_at, 'dd.MM.yyyy HH:mm'), ['class' => 'body-text'])
                . ($p->updater ? Html::tag('div', Html::encode($p->updater->name ?: $p->updater->username), ['class' => 'text-tiny']) : ''),
        ],
        [
            'class' => ActionColumn::class,
            'template' => '{open} {update} {duplicate} {child} {delete}',
            'buttons' => [
                // Черновик по ссылке с токеном (право editContent); остальным — по сессии админки
                'open' => static fn(string $url, Page $p): string => Html::a(
                    '<i class="icon-external-link"></i>',
                    $p->isLive() || !$canEdit ? $pages->url($p) : $pages->previewUrl($p),
                    ['class' => 'btn-action view', 'title' => 'Открыть на сайте', 'target' => '_blank', 'data-pjax' => 0]
                ),
                'duplicate' => static fn(string $url): string => Html::a(
                    '<i class="icon-copy"></i>',
                    $url,
                    ['class' => 'btn-action view', 'title' => 'Дублировать', 'data-method' => 'post']
                ),
                'child' => static fn(string $url, Page $p): string => Html::a(
                    '<i class="icon-plus"></i>',
                    ['create', 'parent' => $p->id],
                    ['class' => 'btn-action view', 'title' => 'Создать вложенную']
                ),
                'delete' => static fn(string $url, Page $p): string => Html::a(
                    '<i class="icon-trash-2"></i>',
                    $url,
                    [
                        'class' => 'btn-action delete',
                        'title' => 'Удалить',
                        'data-method' => 'post',
                        'data-confirm' => 'Удалить страницу «' . $p->title . '» и все вложенные в неё?',
                    ]
                ),
            ],
            'visibleButtons' => [
                'update' => $canEdit,
                'duplicate' => $canManage,
                'child' => $canManage,
                'delete' => $canManage,
            ],
        ],
    ],
]) ?>
