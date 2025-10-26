<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $items */
?>
<div class="tf-section">
    <div class="wg-box">
        <div class="flex items-center justify-between">
            <h5>Лог ошибок</h5>
        </div>
        <div class="wg-table table-product-overview t1">
            <ul class="table-title flex gap20 mb-14"  style="padding: 10px;">
                <li style="width: 115px;"><div class="body-title">Дата/время</div></li>
                <li style="min-width: 80px;"><div class="body-title">IP</div></li>
                <li style="min-width: 50px;"><div class="body-title">Вид</div></li>
                <li style="width: 100%;"><div class="body-title">Сообщение</div></li>
            </ul>
            <div class="divider mb-14"></div>
            <ul class="flex flex-column gap10">
                <?php if (empty($items)): ?>
                    <li class="product-item gap14">
                        <div class="flex items-center justify-between flex-grow gap20">
                            <div class="body-text">—</div>
                            <div class="body-text">—</div>
                            <div class="body-text">—</div>
                            <div class="body-text">Лог пуст</div>
                        </div>
                    </li>
                <?php else: ?>
                    <?php foreach ($items as $row): ?>
                        <li class="product-item gap14" style="padding: 10px;">
                            <div class="flex items-center justify-between flex-grow gap20">
                                <div class="body-text" style="width: 115px;">
                                    <?php
                                        $datetime = strtotime(Html::encode($row['datetime']));
                                        if ($datetime) {
                                            echo date('d.m.Y', $datetime);
                                            echo '<br>';
                                            echo date('H:i:s', $datetime);
                                        }
                                        ?>
                                </div>
                                <div class="body-text" style="min-width: 80px;"><?= Html::encode($row['ip']) ?></div>
                                <div class="body-text" style="min-width: 50px;">
                                    <?php
                                    $level = Html::encode($row['level']);
                                    if ($level === 'info') {
                                        echo '<div class="block-published">info</div>';
                                    } elseif ($level === 'warning') {
                                        echo '<div class="block-pending">warning</div>';
                                    } elseif ($level === 'error') {
                                        echo '<div class="block-not-available">error</div>';
                                    } else {
                                        echo '<div class="block-pending">' . $level . '</div>';
                                    }
                                    ?>
                                </div>
                                <div class="body-text" style="width: 100%;" title="<?= Html::encode($row['message']) ?>">
                                    <?= Html::encode($row['message']) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div class="divider"></div>
        <div class="flex items-center justify-between flex-wrap gap10">
            <div class="wg-filter flex-grow"></div>
            <a class="tf-button style-1" href="<?= Url::to(['log/index']) ?>">Весь лог</a>
        </div>
    </div>
</div>
