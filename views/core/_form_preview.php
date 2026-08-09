<?php

declare(strict_types=1);

/**
 * Предпросмотр формы записи: тот же рендер полей, что в _form.php,
 * но без submit-обвязки и с блокирующим overlay поверх.
 *
 * @var $this  yii\web\View
 * @var $model \Mitisk\Yii2Admin\core\models\AdminModel
 */
?>
<div class="form-preview-wrap">
    <div class="form-preview-banner">
        <i class="fas fa-eye me-1"></i> Предпросмотр — элементы формы неактивны
    </div>
    <div class="row form-preview-body" style="row-gap: 25px">
        <?php
        foreach ($model->getFormFields() as $value) {
            echo $value;
        }
        ?>
        <div class="form-preview-overlay"></div>
    </div>
</div>
