<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\ContentBlockSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var bool $canManage */

use Mitisk\Yii2Admin\assets\ContentBlockAsset;
use Mitisk\Yii2Admin\enums\BlockType;
use Mitisk\Yii2Admin\models\ContentBlock;
use Mitisk\Yii2Admin\models\ContentBlockSearch;
use Mitisk\Yii2Admin\widgets\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

ContentBlockAsset::register($this);
$this->title = $this->params['pageHeaderText'] = 'Текстовые блоки';
$this->params['breadcrumbs'][] = 'Контент';
$this->params['breadcrumbs'][] = $this->title;
?>
<?php if ($canManage): ?>
<div class="wg-box mb-20">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="body-text">Блоки из шаблонов сайта появляются здесь сами при первом показе страницы.</div>
        <div class="flex gap10 flex-wrap">
            <?php foreach (BlockType::cases() as $type): ?>
                <?= Html::a('<i class="icon-plus"></i> ' . Html::encode($type->label()), ['create', 'type' => $type->value], ['class' => 'tf-button style-1']) ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'columns' => [
        [
            'attribute' => 'key',
            'format' => 'raw',
            'value' => static fn(ContentBlock $m): string => Html::tag('code', Html::encode($m->key))
                . ($m->from_code ? ' <span class="badge bg-secondary" title="Создан из шаблона сайта">код</span>' : ''),
        ],
        ['attribute' => 'name', 'filter' => false],
        ['attribute' => 'group', 'filter' => ContentBlockSearch::groupOptions()],
        [
            'attribute' => 'type',
            'filter' => BlockType::options(),
            'value' => static fn(ContentBlock $m): string => $m->getBlockType()->label(),
        ],
        [
            'attribute' => 'is_active',
            'format' => 'raw',
            'filter' => [1 => 'Да', 0 => 'Нет'],
            'value' => static fn(ContentBlock $m): string => Html::tag('span', $m->is_active ? 'Да' : 'Нет', [
                'class' => 'badge ' . ($m->is_active ? 'bg-success' : 'bg-secondary') . ' js-cb-toggle',
                'style' => 'cursor:pointer',
                'data' => ['id' => $m->id, 'url' => Url::to(['toggle'])],
            ]),
        ],
        [
            'attribute' => 'updated_at',
            'filter' => false,
            'format' => 'raw',
            'value' => static fn(ContentBlock $m): string => Yii::$app->formatter->asDatetime($m->updated_at)
                . ($m->updater ? '<br><small>' . Html::encode($m->updater->name ?: $m->updater->username) . '</small>' : ''),
        ],
        [
            'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
            'template' => '{update} {delete}',
            'visibleButtons' => ['delete' => $canManage],
        ],
    ],
]) ?>
