<?php

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\PageForm $form */
/** @var bool $modal */

use Mitisk\Yii2Admin\assets\PageAsset;
use Mitisk\Yii2Admin\enums\PageStatus;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

PageAsset::register($this);
$page = $form->page;
$manage = $form->scenario === $form::SCENARIO_MANAGE;
$this->registerCss(<<<CSS
.hint-block { font-size: 12px; color: #64748b; margin-top: 10px; margin-bottom: 0; }
.page-side .wg-box { position: sticky; top: 20px; }
.page-url { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 13px; color: #475569; }
.page-url code { background: #f1f5f9; padding: 3px 8px; border-radius: 6px; user-select: all; }
.page-editor-tabs .tf-button { width: auto; padding: 0 18px; height: 36px; }
.page-editor-tabs .tf-button.is-active { background: #2563eb; color: #fff; border-color: #2563eb; }
.cb-image-preview { display: inline-block; padding: 8px; border: 1px solid #e2e8f0; border-radius: 12px; }
.cb-image-preview img { display: block; max-width: 320px; max-height: 200px; border-radius: 8px; }
input[type=file].cb-file { display: block; width: 100%; max-width: 420px; font-size: 14px; padding: 10px 14px; height: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
input[type=file].cb-file::file-selector-button { margin-right: 12px; padding: 6px 14px; border: 0; border-radius: 8px; background: #eef2ff; color: #2563eb; font-weight: 600; cursor: pointer; }
details.page-seo summary { cursor: pointer; font-weight: 600; font-size: 16px; margin-bottom: 16px; list-style: none; }
details.page-seo summary::before { content: '▸ '; color: #94a3b8; }
details.page-seo[open] summary::before { content: '▾ '; }
CSS);
?>
<?php $af = ActiveForm::begin([
    'id' => 'page-form',
    'options' => ['enctype' => 'multipart/form-data', 'data-page-form' => '1'],
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{hint}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => ''],
        'errorOptions' => ['class' => 'invalid-feedback d-block'],
        'hintOptions' => ['class' => 'hint-block'],
    ],
]); ?>
<div class="row">
    <div class="<?= $modal ? 'col-12' : 'col-lg-8' ?>">
        <div class="wg-box mb-20">
            <?php if ($manage): ?>
                <fieldset class="name mb-24"><?= $af->field($form, 'title')->textInput(['maxlength' => true, 'data-page-title' => '1', 'placeholder' => 'О компании']) ?></fieldset>
                <div class="row">
                    <div class="col-md-8">
                        <fieldset class="name mb-24">
                            <?= $af->field($form, 'slug')->textInput(['maxlength' => 128, 'data-page-slug' => '1', 'placeholder' => 'o-kompanii'])
                                ->hint('Латиница, цифры и дефис. Адрес складывается из адреса родителя и слага.') ?>
                        </fieldset>
                    </div>
                    <div class="col-md-4">
                        <fieldset class="mb-24" style="padding-top:34px"><?= $af->field($form, 'slug_auto')->checkbox() ?></fieldset>
                    </div>
                </div>
            <?php else: ?>
                <?php // Редактор правит заголовок, но не адрес: слаг не пересобирается ?>
                <fieldset class="name mb-24"><?= $af->field($form, 'title')->textInput(['maxlength' => true]) ?></fieldset>
            <?php endif; ?>
            <?php if (!$page->isNewRecord): ?>
                <div class="page-url mb-24">
                    <span>Адрес: <code>/<?= Html::encode($page->path) ?></code></span>
                    <?= Html::a('Открыть на сайте', $page->isLive() ? Yii::$app->pages->url($page) : Yii::$app->pages->previewUrl($page), ['target' => '_blank']) ?>
                    <a href="#" data-preview-link="<?= Url::to(['preview-link', 'id' => $page->id]) ?>">Ссылка для заказчика</a>
                </div>
            <?php endif; ?>
            <?= $this->render('_editor', ['form' => $form, 'af' => $af]) ?>
            <fieldset class="name mb-24 mt-3"><?= $af->field($form, 'excerpt')->textarea(['rows' => 3])->hint('Короткое описание для списков дочерних страниц и description по умолчанию') ?></fieldset>
        </div>

        <div class="wg-box mb-20">
            <details class="page-seo" <?= $page->seo_title || $page->seo_description || $page->noindex || $page->canonical ? 'open' : '' ?>>
                <summary>SEO</summary>
                <fieldset class="name mb-24"><?= $af->field($form, 'seo_title')->textInput(['maxlength' => true])->hint('Пусто — заголовок страницы') ?></fieldset>
                <fieldset class="name mb-24"><?= $af->field($form, 'seo_description')->textarea(['rows' => 2])->hint('Пусто — анонс или начало текста') ?></fieldset>
                <fieldset class="name mb-24"><?= $af->field($form, 'seo_keywords')->textInput(['maxlength' => true]) ?></fieldset>
                <fieldset class="name mb-24"><?= $af->field($form, 'canonical')->textInput(['placeholder' => 'https://…'])->hint('Пусто — адрес самой страницы') ?></fieldset>
                <?php $og = $page->isNewRecord ? null : $page->getOgImageUrl(); ?>
                <?php if ($og): ?>
                    <div class="mb-24"><div class="cb-image-preview"><?= Html::img($og, ['alt' => '']) ?></div><div class="mt-2"><?= $af->field($form, 'og_image_remove', ['options' => ['class' => 'mb-0']])->checkbox() ?></div></div>
                <?php endif; ?>
                <fieldset class="name mb-24"><?= $af->field($form, 'og_image')->fileInput(['accept' => 'image/*', 'class' => 'cb-file'])->label($og ? 'Заменить OG-картинку' : 'OG-картинка')->hint('JPG, PNG, GIF или WebP; показывается при репосте в соцсети') ?></fieldset>
                <fieldset class="mb-24"><?= $af->field($form, 'noindex')->checkbox()->hint('Страница не попадёт в sitemap и получит robots: noindex') ?></fieldset>
            </details>
        </div>
    </div>

    <div class="<?= $modal ? 'col-12' : 'col-lg-4' ?> page-side">
        <div class="wg-box">
            <fieldset class="select mb-24"><?= $af->field($form, 'status')->dropDownList(PageStatus::options(), ['class' => 'tom-select']) ?></fieldset>
            <fieldset class="name mb-24"><?= $af->field($form, 'published_at')->input('datetime-local')->hint('Пусто — сейчас. Дата в будущем — отложенная публикация') ?></fieldset>
            <?php if ($manage): ?>
                <fieldset class="select mb-24"><?= $af->field($form, 'parent_id')->dropDownList($form->getParentOptions(), ['prompt' => '— корень сайта —', 'class' => 'tom-select']) ?></fieldset>
                <fieldset class="select mb-24"><?= $af->field($form, 'template')->dropDownList($form->getTemplateOptions(), ['class' => 'tom-select'])->hint('Файлы views/page/*.php сайта') ?></fieldset>
                <fieldset class="name mb-24"><?= $af->field($form, 'sort')->input('number')->hint('Меньше — выше среди соседей') ?></fieldset>
            <?php endif; ?>
            <div class="bot">
                <div></div>
                <?= Html::submitButton('Сохранить', ['class' => 'tf-button w208']) ?>
            </div>
        </div>
    </div>
</div>
<?php ActiveForm::end(); ?>
