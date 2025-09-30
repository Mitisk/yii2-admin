<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\helpers\Html;

$this->title = $name;

\Mitisk\Yii2Admin\assets\PageNotFoundAsset::register($this);

if ($exception instanceof \yii\web\HttpException) {
    $statusCode = $exception->statusCode;
} else {
    $statusCode = $exception->getCode(); // Fallback for other exceptions
}
?>
<section class="wg-box page_404">
    <div class="text-center">
        <div class="four_zero_four_bg">
            <h1 class="text-center"><?= $statusCode ?></h1>
        </div>

        <div class="contant_box_404">
            <h3 class="h2">
                Что-то пошло не так!
            </h3>

            <p><?= nl2br(Html::encode($message)) ?></p>

        </div>
    </div>
</section>