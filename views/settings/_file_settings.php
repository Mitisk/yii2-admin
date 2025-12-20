<?php
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/** @var \Mitisk\Yii2Admin\models\Settings[] $settings */

// Index settings by attribute for easier access
$items = ArrayHelper::index($settings, 'attribute');

?>
<div class="wg-box">
    <div class="left js-change-header">
        <h5 class="mb-4">Хранилище файлов</h5>
        <div class="body-text">Настройки сохранения загружаемых файлов (Local, FTP, S3)</div>
    </div>

    <div class="right flex-grow">
        
        <!-- Storage Type -->
        <fieldset class="mb-10">
            <?php 
                $attr = 'storage_type';
                if (isset($items[$attr])): 
                    $curr = $items[$attr];
            ?>
            <div class="body-title mb-10"><?= Html::encode($curr->label ?: $attr) ?>
                <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', '<?= $attr ?>');"></i>
            </div>
            <?= Html::dropDownList("Settings[{$curr->id}]", $curr->value, [
                'local' => 'Локальное хранилище',
                'ftp' => 'FTP',
                's3' => 'S3 (Amazon, MinIO, etc.)',
            ], ['class' => 'select flex-grow mb-10 js-storage-type-select', 'id' => 'storage-type-select']) ?>
            
            <?php if ($curr->description) : ?>
                <div class="body-text mb-24"><?= $curr->description ?></div>
            <?php endif; ?>
            <?php endif; ?>
        </fieldset>

        <!-- FTP Settings -->
        <div id="settings-ftp" style="display: none;">
            <h6 class="mb-10">Настройки FTP</h6>
            <?php 
            $ftpAttrs = ['ftp_host', 'ftp_port', 'ftp_user', 'ftp_pass', 'ftp_path'];
            foreach ($ftpAttrs as $attr):
                if (isset($items[$attr])):
                    $curr = $items[$attr];
            ?>
            <fieldset class="mb-10">
                <div class="body-title mb-10"><?= Html::encode($curr->label ?: $attr) ?></div>
                <?= Html::textInput("Settings[{$curr->id}]", $curr->value, ['class' => 'form-control']) ?>
                <?php if ($attr == 'ftp_pass'): ?>
                     <div class="body-text mb-24">Пароль хранится в открытом виде</div>
                <?php endif; ?>
            </fieldset>
            <?php endif; endforeach; ?>
        </div>

        <!-- S3 Settings -->
        <div id="settings-s3" style="display: none;">
            <h6 class="mb-10">Настройки S3</h6>
            <?php 
            $s3Attrs = ['s3_endpoint', 's3_region', 's3_bucket', 's3_key', 's3_secret', 's3_prefix', 's3_path_style'];
            foreach ($s3Attrs as $attr):
                if (isset($items[$attr])):
                    $curr = $items[$attr];
            ?>
            <fieldset class="mb-10">
                <div class="body-title mb-10"><?= Html::encode($curr->label ?: $attr) ?></div>
                <?php if ($curr->type === 'boolean'): ?>
                     <?= Html::dropDownList("Settings[{$curr->id}]", $curr->value, ['0' => 'Нет', '1' => 'Да'], ['class' => 'select flex-grow']) ?>
                <?php else: ?>
                     <?= Html::textInput("Settings[{$curr->id}]", $curr->value, ['class' => 'form-control']) ?>
                <?php endif; ?>
                <?php if ($curr->description) : ?>
                    <div class="body-text mb-24"><?= $curr->description ?></div>
                <?php endif; ?>
            </fieldset>
            <?php endif; endforeach; ?>
        </div>

    </div>
</div>
<?php
$this->registerJs(
    "    const selector = document.getElementById('storage-type-select');
    const ftpBlock = document.getElementById('settings-ftp');
    const s3Block = document.getElementById('settings-s3');

    function toggleSettings() {
        const val = selector.value;
        ftpBlock.style.display = val === 'ftp' ? 'block' : 'none';
        s3Block.style.display = val === 's3' ? 'block' : 'none';
    }

    if (selector) {
        selector.addEventListener('change', toggleSettings);
        toggleSettings();
    }"
);
