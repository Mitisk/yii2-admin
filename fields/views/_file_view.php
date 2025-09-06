<?php
use yii\helpers\Html;

/** @var $files \Mitisk\Yii2Admin\models\File[]  */
?>
    <div class="upload-image mb-16">
        <?php foreach ($files as $file): ?>
            <div class="item">
                <?php if ($file->isImage()) : ?>
                    <img src="<?= $file->path ?>" alt="<?= $file->alt_attribute ?>">
                <?php else: ?>

                    <?=
                            Html::a(
                                Html::encode($file->filename ?: $file->path),
                                $file->path,
                                ['target' => '_blank', 'rel' => 'noopener', 'class' => 'file-link']
                            );
                    ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
