<?php
/** @var $files \Mitisk\Yii2Admin\models\File[]  */
?>
    <div class="upload-image mb-16">
        <?php foreach ($files as $file): ?>
            <div class="item">
                <img src="<?= $file->path ?>" alt="<?= $file->alt_attribute ?>">
            </div>
        <?php endforeach; ?>
    </div>