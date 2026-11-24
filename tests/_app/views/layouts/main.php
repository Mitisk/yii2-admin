<?php

/** Лейаут «сайта» тестового приложения: только содержимое страницы. */

/** @var yii\web\View $this */
/** @var string $content */
?>
<?php $this->beginPage() ?><html><body><?php $this->beginBody() ?><?= $content ?><?php $this->endBody() ?></body></html><?php $this->endPage() ?>
