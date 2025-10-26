<?php
/* @var $this yii\web\View */
/** @var array $items */
$this->title = 'Лог проекта';

$this->params['breadcrumbs'][] = $this->title;
?>

<div class="wg-box">
    <div style="max-height: 70vh; overflow:auto; border:1px solid #eee; padding:10px;">
        <?php foreach ($items as $line): ?>
            <div class="body-text" style="white-space: pre-wrap;"><?= htmlspecialchars($line) ?></div>
        <?php endforeach; ?>
    </div>
</div>
