<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\ContentBlockForm $form */
/** @var bool $modal */

use Mitisk\Yii2Admin\assets\ContentBlockAsset;
use Mitisk\Yii2Admin\assets\TrumbowygAsset;
use Mitisk\Yii2Admin\dto\ListItem;
use Mitisk\Yii2Admin\enums\BlockType;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$type = $form->getBlockType();
$manage = $form->scenario === $form::SCENARIO_MANAGE;
ContentBlockAsset::register($this);
if ($type === BlockType::Html) {
    TrumbowygAsset::register($this);
}
$block = $form->block;
?>
<?php $af = ActiveForm::begin([
    'id' => 'content-block-form',
    'options' => ['enctype' => 'multipart/form-data'],
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{hint}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'errorOptions' => ['class' => 'invalid-feedback d-block'],
    ],
]); ?>
<div class="wg-box">
    <?php if ($block->hint): ?>
        <div class="block-warning type-main w-full mb-15"><div class="body-title-2"><?= Html::encode($block->hint) ?></div></div>
    <?php endif; ?>

    <?php if ($manage): ?>
        <div class="row">
            <div class="col-md-4"><?= $af->field($form, 'name')->textInput(['maxlength' => true]) ?></div>
            <div class="col-md-4"><?= $af->field($form, 'key')->textInput(['maxlength' => 128, 'readonly' => !$block->isNewRecord && $block->from_code])
                ->hint('В шаблоне: <code>ContentBlock::widget([\'key\' => \'' . Html::encode($form->key ?: 'group.name') . '\'])</code>') ?></div>
            <div class="col-md-4"><?= $af->field($form, 'group')->textInput(['maxlength' => 64])->hint('Пусто — первая часть ключа') ?></div>
        </div>
        <?= $af->field($form, 'hint')->textInput(['maxlength' => true])->hint('Где блок на сайте, ограничения по длине') ?>
        <?= Html::activeHiddenInput($form, 'type') ?>
    <?php endif; ?>

    <?php if ($type === BlockType::Text): ?>
        <?= $af->field($form, 'text')->textarea(['rows' => 3]) ?>
    <?php elseif ($type === BlockType::Html): ?>
        <?= $af->field($form, 'text')->textarea(['rows' => 10, 'data-cb-editor' => 'html']) ?>
    <?php elseif ($type === BlockType::Link): ?>
        <div class="row">
            <div class="col-md-5"><?= $af->field($form, 'linkText')->textInput() ?></div>
            <div class="col-md-5"><?= $af->field($form, 'linkUrl')->textInput(['placeholder' => '/page или https://…']) ?></div>
            <div class="col-md-2"><?= $af->field($form, 'linkTarget')->dropDownList(['_self' => 'В этом окне', '_blank' => 'В новом окне']) ?></div>
        </div>
    <?php elseif ($type === BlockType::Image): ?>
        <?php $url = $block->isNewRecord ? null : Yii::$app->blocks->imageUrl((string)$block->key); ?>
        <?php if ($url): ?>
            <p><?= Html::img($url, ['style' => 'max-width:320px;max-height:200px;border-radius:8px']) ?></p>
            <?= $af->field($form, 'imageRemove')->checkbox() ?>
        <?php endif; ?>
        <?= $af->field($form, 'imageFile')->fileInput(['accept' => 'image/*']) ?>
        <div class="row">
            <div class="col-md-6"><?= $af->field($form, 'imageAlt')->textInput() ?></div>
            <div class="col-md-6"><?= $af->field($form, 'imageTitle')->textInput() ?></div>
        </div>
    <?php else: ?>
        <?php if ($manage): ?>
            <?= $af->field($form, 'itemFields')->checkboxList(array_combine(ListItem::FIELDS, ['Заголовок', 'Текст', 'Ссылка', 'Картинка'])) ?>
        <?php endif; ?>
        <div class="body-title mb-10">Пункты</div>
        <div id="cb-items" class="flex flex-column gap10 mb-15">
            <?php foreach ($form->items as $rowKey => $row): ?>
                <?= $this->render('_item', ['form' => $form, 'rowKey' => (string)$rowKey, 'row' => $row]) ?>
            <?php endforeach; ?>
        </div>
        <template id="cb-item-template"><?= $this->render('_item', ['form' => $form, 'rowKey' => '__i__', 'row' => []]) ?></template>
        <button type="button" class="tf-button style-1 mb-15" id="cb-item-add"><i class="icon-plus"></i> Добавить пункт</button>
        <?= Html::error($form, 'items', ['class' => 'invalid-feedback d-block']) ?>
    <?php endif; ?>

    <?= $af->field($form, 'is_active')->checkbox() ?>
    <?= Html::error($form, 'text', ['class' => 'invalid-feedback d-block']) ?>

    <div class="bot">
        <div></div>
        <?= Html::submitButton('Сохранить', ['class' => 'tf-button w208']) ?>
    </div>
</div>
<?php ActiveForm::end(); ?>
