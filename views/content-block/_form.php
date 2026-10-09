<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\ContentBlockForm $form */
/** @var bool $modal */

use Mitisk\Yii2Admin\assets\ContentBlockAsset;
use Mitisk\Yii2Admin\enums\BlockType;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$type = $form->getBlockType();
$manage = $form->scenario === $form::SCENARIO_MANAGE;
ContentBlockAsset::register($this);
$block = $form->block;

// Оформление в духе остальных форм модуля (см. seo-rule/_form.php)
$this->registerCss(<<<CSS
.hint-block { font-size: 12px; color: #64748b; margin-top: 10px; margin-bottom: 0; }
.cb-usage { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 12px 16px;
    border-radius: 12px; background: #f1f5f9; font-size: 13px; color: #475569; }
.cb-usage code { margin: 0; font-size: 13px; padding: 4px 10px; border-radius: 8px; background: #fff; color: #0f172a;
    border: 1px solid #e2e8f0; user-select: all; white-space: nowrap; overflow-x: auto; max-width: 100%; }
.cb-image-preview { display: inline-block; padding: 8px; border: 1px solid #e2e8f0; border-radius: 12px; }
.cb-image-preview img { display: block; max-width: 320px; max-height: 200px; border-radius: 8px; }
input[type=file].cb-file { display: block; width: 100%; max-width: 420px; font-size: 14px; padding: 10px 14px; height: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
input[type=file].cb-file::file-selector-button { margin-right: 12px; padding: 6px 14px; border: 0; border-radius: 8px;
    background: #eef2ff; color: #2563eb; font-weight: 600; cursor: pointer; }
CSS);
?>
<?php $af = ActiveForm::begin([
    'id' => 'content-block-form',
    'options' => ['enctype' => 'multipart/form-data'],
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{hint}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => ''],
        'errorOptions' => ['class' => 'invalid-feedback d-block'],
        'hintOptions' => ['class' => 'hint-block'],
    ],
]); ?>

<?php if ($block->hint): ?>
    <div class="block-warning type-main w-full mb-20">
        <i class="icon-info"></i>
        <div class="body-title-2"><?= Html::encode($block->hint) ?></div>
    </div>
<?php endif; ?>

<?php if ($manage): ?>
<div class="wg-box mb-20">
    <div class="body-title mb-10" style="font-size: 16px;">Блок</div>
    <div class="row">
        <div class="col-md-4">
            <fieldset class="name mb-24">
                <?= $af->field($form, 'name')->textInput(['maxlength' => true, 'placeholder' => 'Телефон в шапке']) ?>
            </fieldset>
        </div>
        <div class="col-md-4">
            <fieldset class="name mb-24">
                <?= $af->field($form, 'key')->textInput([
                    'maxlength' => 128,
                    'placeholder' => 'header.phone',
                    'readonly' => !$block->isNewRecord && $block->from_code,
                ])->hint($block->from_code ? 'Задан в шаблоне сайта' : 'Латиница, цифры, «.», «-», «_»') ?>
            </fieldset>
        </div>
        <div class="col-md-4">
            <fieldset class="name mb-24">
                <?= $af->field($form, 'group')->textInput(['maxlength' => 64, 'placeholder' => 'header'])
                    ->hint('Пусто — первая часть ключа') ?>
            </fieldset>
        </div>
    </div>
    <fieldset class="name mb-24">
        <?= $af->field($form, 'hint')->textInput(['maxlength' => true, 'placeholder' => 'Где блок на сайте, ограничения по длине']) ?>
    </fieldset>
    <?php if (!$block->isNewRecord): ?>
        <div class="cb-usage">
            <span>В шаблоне сайта:</span>
            <code><?= Html::encode("ContentBlock::widget(['key' => '" . $block->key . "'])") ?></code>
        </div>
    <?php endif; ?>
    <?= Html::activeHiddenInput($form, 'type') ?>
</div>
<?php endif; ?>

<div class="wg-box">
    <div class="body-title mb-10" style="font-size: 16px;"><?= Html::encode($type->label()) ?></div>

    <?php if ($type === BlockType::Text): ?>
        <fieldset class="name mb-24">
            <?= $af->field($form, 'text')->textarea(['rows' => 3])->label('Значение') ?>
        </fieldset>
    <?php elseif ($type === BlockType::Html): ?>
        <fieldset class="name mb-24">
            <?= $af->field($form, 'text')->textarea(['rows' => 10, 'data-cb-editor' => 'html'])->label('Содержимое') ?>
        </fieldset>
    <?php elseif ($type === BlockType::Link): ?>
        <div class="row">
            <div class="col-md-5">
                <fieldset class="name mb-24"><?= $af->field($form, 'linkText')->textInput(['placeholder' => 'Публичная оферта']) ?></fieldset>
            </div>
            <div class="col-md-4">
                <fieldset class="name mb-24">
                    <?= $af->field($form, 'linkUrl')->textInput(['placeholder' => '/offer или https://…'])
                        ->hint('Начинается с /, #, http(s)://, mailto: или tel:') ?>
                </fieldset>
            </div>
            <div class="col-md-3">
                <fieldset class="select mb-24">
                    <?= $af->field($form, 'linkTarget')->dropDownList(
                        ['_self' => 'В этом окне', '_blank' => 'В новом окне'],
                        ['class' => 'tom-select']
                    ) ?>
                </fieldset>
            </div>
        </div>
    <?php elseif ($type === BlockType::Image): ?>
        <?php $url = $block->isNewRecord ? null : Yii::$app->blocks->imageUrl((string)$block->key); ?>
        <?php if ($url): ?>
            <div class="mb-24">
                <div class="body-title mb-10">Текущая картинка</div>
                <div class="cb-image-preview"><?= Html::img($url, ['alt' => '']) ?></div>
                <div class="mt-2"><?= $af->field($form, 'imageRemove', ['options' => ['class' => 'mb-0']])->checkbox() ?></div>
            </div>
        <?php endif; ?>
        <fieldset class="name mb-24">
            <?= $af->field($form, 'imageFile')->fileInput(['accept' => 'image/*', 'class' => 'cb-file'])
                ->label($url ? 'Заменить картинку' : 'Картинка')
                ->hint('JPG, PNG, GIF или WebP') ?>
        </fieldset>
        <div class="row">
            <div class="col-md-6">
                <fieldset class="name mb-24"><?= $af->field($form, 'imageAlt')->textInput()->hint('Описание для поисковиков и экранных дикторов') ?></fieldset>
            </div>
            <div class="col-md-6">
                <fieldset class="name mb-24"><?= $af->field($form, 'imageTitle')->textInput()->hint('Подсказка при наведении') ?></fieldset>
            </div>
        </div>
    <?php endif; ?>

    <fieldset class="mb-24">
        <?= $af->field($form, 'is_active')->checkbox()->hint('Выключенный блок не выводится на сайте') ?>
    </fieldset>
    <?= Html::error($form, 'text', ['class' => 'invalid-feedback d-block mb-15']) ?>

    <div class="bot">
        <div></div>
        <?= Html::submitButton('Сохранить', ['class' => 'tf-button w208']) ?>
    </div>
</div>
<?php ActiveForm::end(); ?>
