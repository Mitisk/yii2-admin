<?php
/**
 * @var yii\web\View $this
 * @var Mitisk\Yii2Admin\models\SeoRule $searchModel
 * @var yii\data\ActiveDataProvider $dataProvider
 */

use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

$this->title = $this->params['pageHeaderText'] = 'SEO-правила';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="wg-box mb-20">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
        </div>

        <?= Html::a('<i class="icon-plus"></i> Добавить правило', ['create'], ['class' => 'tf-button style-1']) ?>
    </div>
</div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'pattern',
                'format' => 'raw',
                'value' => function ($model) {
                    $pattern = $model->pattern;
                    $code = Html::tag('code', Html::encode($pattern));

                    // Если паттерн похож на статический путь — делаем прямую ссылку
                    $clean = trim($pattern, '#/~@iu');
                    $clean = ltrim($clean, '^');
                    $clean = rtrim($clean, '$');
                    if (preg_match('#^/[\w\-/]+$#', $clean)) {
                        $link = Html::a(
                            '<i class="icon-external-link" style="font-size:13px"></i>',
                            $clean,
                            ['target' => '_blank', 'title' => $clean, 'style' => 'margin-left:6px;opacity:.6']
                        );
                        return $code . $link;
                    }

                    return $code;
                },
            ],
            'title',
            [
                'attribute' => 'priority',
                'filter' => false,
                'headerOptions' => ['style' => 'width:100px'],
            ],
            [
                'attribute' => 'is_active',
                'format' => 'raw',
                'filter' => [1 => 'Да', 0 => 'Нет'],
                'headerOptions' => ['style' => 'width:100px'],
                'value' => function ($model) {
                    $class = $model->is_active ? 'badge bg-success' : 'badge bg-secondary';
                    $text = $model->is_active ? 'Да' : 'Нет';
                    return Html::tag('span', $text, [
                        'class' => $class . ' seo-toggle',
                        'style' => 'cursor:pointer',
                        'data-id' => $model->id,
                        'title' => 'Нажмите для переключения',
                    ]);
                },
            ],
            [
                'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
                'template' => '{update} {delete}',
            ],
        ],
    ]); ?>


<?php
$toggleUrl = \yii\helpers\Url::to(['/admin/seo-rule/toggle']);
$js = <<<JS
$(document).on('click', '.seo-toggle', function(e) {
    e.preventDefault();
    var badge = $(this);
    var id = badge.data('id');
    $.ajax({
        url: '{$toggleUrl}',
        method: 'POST',
        data: { id: id, _csrf: yii.getCsrfToken() },
        success: function(res) {
            if (res.success) {
                if (res.is_active) {
                    badge.removeClass('bg-secondary').addClass('bg-success').text('Да');
                } else {
                    badge.removeClass('bg-success').addClass('bg-secondary').text('Нет');
                }
            }
        }
    });
});
JS;
$this->registerJs($js);
?>
