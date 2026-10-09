<?php

/** @var yii\web\View $this */
/** @var string $content */

use Mitisk\Yii2Admin\assets\AppAsset;
use Mitisk\Yii2Admin\widgets\Alert;
use yii\helpers\Html;

AppAsset::register($this);
$this->registerCsrfMetaTags();
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
    <meta charset="<?= Html::encode(Yii::$app->charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="body" style="padding:16px;background:#fff">
<?php $this->beginBody() ?>
<?= Alert::widget() ?>
<?= $content ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
