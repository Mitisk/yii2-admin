<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use Mitisk\Yii2Admin\assets\TrumbowygAsset;

/* @var $this yii\web\View */
/* @var $model Mitisk\Yii2Admin\models\AdminModelInfo */

$this->title = 'Редактирование инструкции';
$this->params['breadcrumbs'][] = $this->title;

TrumbowygAsset::register($this);
?>

<div class="wg-box">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'content')->textarea(['id' => 'editor-content'])->label(false) ?>

    <div class="bot mt-5">
        <div class="list-box-value mb-10">
            <div>
                <?= Html::submitButton('Сохранить', ['class' => 'tf-button w208']) ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php 
$this->registerJs("
    $('#editor-content').trumbowyg({
        lang: 'ru',
        imageWidthModalEdit: true,
        autogrow: true,
        btns: [
            ['undo', 'redo'],
            ['removeformat'],
            ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
            ['unorderedList', 'orderedList'],
            ['table'],
            ['emoji'],
            ['link', 'insertImage'],
            ['formatting'],
            ['strong', 'em', 'del'],
            ['superscript', 'subscript'],
            ['horizontalRule'],
            ['viewHTML'],
            
            ['fullscreen']
        ]
    });
");
