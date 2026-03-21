<?php
/**
 * @var yii\web\View $this
 * @var Mitisk\Yii2Admin\models\SeoRule $model
 * @var yii\widgets\ActiveForm $form
 */

use yii\helpers\Html;
use yii\widgets\ActiveForm;
?>

<?php
$this->registerCss('.hint-block { font-size: 12px; color: #64748b; margin-top: 10px; margin-bottom: 0; }');

$form = ActiveForm::begin([
    'id' => 'seo-rule-form',
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{hint}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => ''],
        'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
        'hintOptions' => ['class' => 'hint-block'],
    ],
]); ?>

<div class="wg-box mb-20">
    <div class="body-title mb-10" style="font-size: 16px;">URL и приоритет</div>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'pattern')->textInput([
            'maxlength' => true,
            'placeholder' => '/catalog/.* или #^/news/\d+#',
        ])->hint('Регулярное выражение. Без разделителей оборачивается в <code>#...#iu</code>. С разделителями (<code>/</code>, <code>#</code>, <code>~</code>, <code>@</code>) — как есть.') ?>
    </fieldset>

    <div class="row">
        <div class="col-md-6">
            <fieldset class="name mb-24">
                <?= $form->field($model, 'priority')->textInput([
                    'type' => 'number',
                    'placeholder' => '0',
                ])->hint('Чем выше — тем раньше применится правило.') ?>
            </fieldset>
        </div>
        <div class="col-md-6">
            <fieldset class="name mb-24" style="padding-top: 30px;">
                <?= $form->field($model, 'is_active')->checkbox() ?>
            </fieldset>
        </div>
    </div>
</div>

<div class="wg-box mb-20">
    <div class="body-title mb-10" style="font-size: 16px;">Мета-теги</div>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'title')->textInput([
            'maxlength' => true,
            'placeholder' => '{category_name} — купить в интернет-магазине ({count} товаров)',
        ])->hint('Поддерживает плейсхолдеры: <code>{variable}</code>') ?>
    </fieldset>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'description')->textarea([
            'rows' => 3,
            'placeholder' => 'Большой выбор {category_name}. В наличии {count} товаров.',
        ]) ?>
    </fieldset>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'keywords')->textInput([
            'maxlength' => true,
            'placeholder' => '{category_name}, купить, интернет-магазин',
        ]) ?>
    </fieldset>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'robots')->textInput([
            'maxlength' => true,
            'placeholder' => 'index, follow',
        ])->hint('Например: <code>index, follow</code>, <code>noindex, nofollow</code>') ?>
    </fieldset>
</div>

<div class="wg-box">
    <div class="body-title mb-10" style="font-size: 16px;">Open Graph</div>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'og_title')->textInput([
            'maxlength' => true,
            'placeholder' => 'OG Title (если пусто — подставится title)',
        ]) ?>
    </fieldset>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'og_description')->textarea([
            'rows' => 3,
            'placeholder' => 'OG Description (если пусто — подставится description)',
        ]) ?>
    </fieldset>

    <fieldset class="name mb-24">
        <?= $form->field($model, 'og_image')->textInput([
            'maxlength' => true,
            'placeholder' => 'https://example.com/image.jpg',
        ]) ?>
    </fieldset>

    <div class="bot">
        <div></div>
        <?= Html::submitButton('Сохранить', ['class' => 'tf-button w208']) ?>
    </div>
</div>

<?php ActiveForm::end(); ?>
