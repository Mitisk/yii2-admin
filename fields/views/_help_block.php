<?php
/** @var $field \Mitisk\Yii2Admin\fields\DateField  */

?>

<?php if ($field->description) { ?>
    <div class="block-warning type-main w-full mt-4 mb-24">
        <i class="icon-alert-octagon"></i>
        <div class="body-title-2"><?= $field->description ?></div>
    </div>
<?php } ?>