<?php
use yii\helpers\Html;
use Mitisk\Yii2Admin\widgets\GridView;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminUser */
/* @var $provider \yii\data\ActiveDataProvider */

$this->params['breadcrumbs'][] = ['label' => 'Пользователи'];
$this->title = $this->params['pageHeaderText'] = 'Пользователи';
?>
<div class="wg-box">
    <div class="flex items-center justify-between gap10 flex-wrap">
        <div class="wg-filter flex-grow">
            <form class="form-search">
                <fieldset class="name">
                    <input type="text" placeholder="Поиск..." class="" name="<?= $model->formName() ?>[search]" tabindex="2" aria-required="true"
                           value="<?= \yii\helpers\ArrayHelper::getValue(Yii::$app->request->get(), $model->formName() . '.search') ?>">
                </fieldset>
                <div class="button-submit">
                    <button class="" type="submit"><i class="icon-search"></i></button>
                </div>
            </form>
        </div>
        <?= Html::a("<i class=\"icon-plus\"></i> Добавить пользователя", ['create'], ['class' => 'tf-button style-1']) ?>

        <?php if ($model->hasSettings()) : ?>
            <?= Html::a("<i class=\"icon-settings\"></i>", ['/admin/settings/', 'modelName' => $model::class], ['class' => 'tf-button']) ?>
        <?php endif; ?>
    </div>
    <?= $this->render('_list', [
        'model' => $model,
        'provider' => $provider
    ]) ?>
</div>