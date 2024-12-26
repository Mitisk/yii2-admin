<?php
/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\Menu */
?>
<?php if(!$model->not_editable): ?>
<form action="" class="flex flex-column gap24 form-search">

    <div class="row">

        <div class="col-xl-6 mb-20">
            <fieldset class="name mb-24">
                <input class="js-change-input" type="text" placeholder="Название" name="name" tabindex="0" value="<?= $model->name ?>" data-alias="<?= $model->alias ? $model->alias : 'new' ?>" aria-required="true" required="">
            </fieldset>
        </div>

        <div class="col-xl-6 mb-20 ">

            <fieldset class="name mb-24">
                <input class="js-change-input" type="text" placeholder="Алиас" name="alias" tabindex="0" value="<?= $model->alias ?>" data-alias="<?= $model->alias ? $model->alias : 'new' ?>" aria-required="true" required="" <?= $model->alias ? 'readonly' : '' ?>>
            </fieldset>

            <?php if($model->id): ?>
            <div class="button-submit">
                <a href="/admin/menu/delete/?id=<?= $model->id ?>"><i class="icon-trash"></i></a>
            </div>
            <?php endif; ?>

        </div>

    </div>

</form>
<?php endif; ?>