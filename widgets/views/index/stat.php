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
 */

use yii\helpers\Html;

$body = '<div class="flex items-center gap14">'
    . '<div class="image"><i class="' . Html::encode($icon) . '"></i></div>'
    . '<div>'
    . '<div class="body-text mb-2">' . Html::encode($title) . '</div>'
    . '<h4>' . Html::encode($value) . '</h4>'
    . ($note !== '' ? '<div class="body-text">' . Html::encode($note) . '</div>' : '')
    . '</div></div>';
?>
<div class="wg-chart-default dashboard-top-widget-item" data-name="<?= Html::encode($name) ?>">
    <div class="dropdown default">
        <button class="btn btn-secondary dropdown-toggle" data-bs-offset="0,-16" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="icon-more"><i class="icon-more-horizontal"></i></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dashboard-top-widget-item-move" href="javascript:void(0);">Переместить</a></li>
            <li><a class="dashboard-top-widget-item-hide" href="javascript:void(0);">Скрыть</a></li>
        </ul>
    </div>
    <?php if ($url !== '') : ?>
        <a href="<?= Html::encode($url) ?>" class="flex items-center justify-between" style="color: inherit; text-decoration: none;"><?= $body ?></a>
    <?php else : ?>
        <div class="flex items-center justify-between"><?= $body ?></div>
    <?php endif; ?>
</div>
