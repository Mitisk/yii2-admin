<?php
/**
 * Карточка статистики дашборда (Mitisk\Yii2Admin\widgets\StatWidget).
 *
 * @var yii\web\View $this
 * @var string $name  короткое имя класса виджета (data-name — по нему дашборд прячет и двигает карточку)
 * @var string $title
 * @var string $icon
 * @var string $value
 * @var string $note
 * @var string $url
 * @var string $tone   цвет иконки и графика: blue | green | red | orange | gray
 * @var string $trend  короткая плашка рядом с числом («+3 за неделю»)
 * @var float[] $spark значения по дням для графика; пусто — графика нет
 */

use yii\helpers\Html;

$chart = '';
if ($spark) {
    // Столбики по дням: самый высокий — во всю высоту, нулевой день — тонкая черта
    $max = max($spark) ?: 1;
    $step = 9;
    $height = 30;
    $bars = '';
    foreach (array_values($spark) as $i => $point) {
        $barHeight = $point > 0 ? max(4, (int) round($point / $max * $height)) : 2;
        $bars .= '<rect x="' . ($i * $step) . '" y="' . ($height - $barHeight) . '" width="6" height="' . $barHeight
            . '" rx="2"' . ($point > 0 ? '' : ' class="is-empty"') . '></rect>';
    }
    $chart = '<svg class="db-stat__spark" viewBox="0 0 ' . (count($spark) * $step - 3) . ' ' . $height . '" width="'
        . (count($spark) * $step - 3) . '" height="' . $height . '" aria-hidden="true" focusable="false">' . $bars . '</svg>';
}

$body = '<span class="db-stat__icon db-stat__icon--' . $tone . '"><i class="' . Html::encode($icon) . '"></i></span>'
    . '<span class="db-stat__body">'
    . '<span class="db-stat__title">' . Html::encode($title) . '</span>'
    . '<span class="db-stat__line">'
    . '<span class="db-stat__value">' . Html::encode($value) . '</span>'
    . ($trend !== '' ? '<span class="db-stat__trend db-stat__trend--' . $tone . '">' . Html::encode($trend) . '</span>' : '')
    . '</span>'
    . ($note !== '' ? '<span class="db-stat__note">' . Html::encode($note) . '</span>' : '')
    . '</span>'
    . ($chart !== '' ? '<span class="db-stat__chart db-stat__chart--' . $tone . '">' . $chart . '</span>' : '');
?>
<div class="wg-chart-default dashboard-top-widget-item db-card" data-name="<?= Html::encode($name) ?>">
    <div class="dropdown default">
        <button class="btn btn-secondary dropdown-toggle" data-bs-offset="0,-16" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Действия с виджетом">
            <span class="icon-more"><i class="icon-more-horizontal"></i></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dashboard-top-widget-item-move" href="javascript:void(0);">Переместить</a></li>
            <li><a class="dashboard-top-widget-item-hide" href="javascript:void(0);">Скрыть</a></li>
        </ul>
    </div>
    <?php if ($url !== '') : ?>
        <a href="<?= Html::encode($url) ?>" class="db-stat"><?= $body ?></a>
    <?php else : ?>
        <div class="db-stat"><?= $body ?></div>
    <?php endif; ?>
</div>
