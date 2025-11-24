<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model Mitisk\Yii2Admin\models\EmailTemplate */
/* @var $form yii\widgets\ActiveForm */

Mitisk\Yii2Admin\assets\TrumbowygAsset::register($this);

// Уникальный ID для textarea
$fieldId = Html::getInputId($model, 'body');
?>
<?php $form = ActiveForm::begin([
    'id' => 'email-template-form',
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => 'mb-15'],
        'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
    ],
]); ?>
    <div class="wg-box email-template-form">

        <div class="row">
            <div class="col-md-6">
                <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
            </div>
            <div class="col-md-6">
                <?= $form->field($model, 'slug')->textInput(['maxlength' => true]) ?>
            </div>
        </div>

        <?= $form->field($model, 'subject')->textInput(['maxlength' => true]) ?>

        <fieldset class="mb-15">
        <?= $form->field($model, 'body')->textarea(['rows' => 10, 'id' => $fieldId]) ?>
        </fieldset>

        <!--div class="panel panel-default mb-15">
            <div class="panel-heading"><strong>Переменные шаблона</strong> <small>(найдены в теле письма)</small></div>
            <div class="panel-body">
                <div id="vars-container">
                    <p class="text-muted">Введите переменные в формате <code>{{variable_name}}</code> в редакторе выше, и они появятся здесь.</p>
                </div>
            </div>
        </div-->

        <div class="wg-table table-all-attribute">
            <ul class="table-title flex gap20 mb-14">
                <li class="email-template-name">
                    <div class="body-title">Название</div>
                </li>
                <li>
                    <div class="body-title">Описание</div>
                </li>
                <li>
                    <div class="body-title"></div>
                </li>
            </ul>
            <ul class="flex flex-column" id="vars-container">



            </ul>
        </div>

        <?= $form->field($model, 'active')->checkbox() ?>
        <div class="bot">
            <div></div>
            <?= Html::submitButton('Сохранить', ['class' => 'tf-button w208']) ?>
        </div>
    </div>



<?php ActiveForm::end(); ?>
<?php
// Передаем существующие настройки (params) в JS
$existingParams = $model->params ? json_encode($model->params) : '{}';

$js = <<<JS
$(document).ready(function() {
    var fieldId = '#{$fieldId}';
    var savedParams = {$existingParams};

    $(fieldId).trumbowyg({
        lang: "ru",
        imageWidthModalEdit: true,
        autogrow: true,
        btns: [
            ['viewHTML'],
            ['undo', 'redo'], // Форматирование для сокращения примера
            ['formatting'],
            ['strong', 'em', 'del'],
            ['superscript', 'subscript'],
            ['link'],
            ['insertImage'],
            ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
            ['unorderedList', 'orderedList'],
            ['horizontalRule'],
            ['removeformat'],
            ['fullscreen']
        ]
    }).on('tbwchange', function(){
        parseVariables();
    }).on('tbwinit', function(){
        parseVariables();
    });

    function parseVariables() {
        var content = $(fieldId).trumbowyg('html');
        var regex = /{{([\w\d_]+)}}/g; 
        var matches = [];
        var match;
        
        while ((match = regex.exec(content)) !== null) {
            if(matches.indexOf(match[1]) === -1) {
                matches.push(match[1]);
            }
        }

        renderParamsInputs(matches);
    }

    // Рендер полей настроек
    function renderParamsInputs(vars) {
        var container = $('#vars-container');
        
        if (vars.length === 0) {
            container.html('<div class="block-warning type-main w-full"><i class="icon-alert-octagon"></i><div class="body-title-2">Нет переменных. Используйте формат {{name}} в тексте шаблона письма.</div></div>');
            return;
        }

        var currentHtml = '';
        
        vars.forEach(function(v) {
            var isRequired = (savedParams[v] && savedParams[v].required == 1) ? 'checked' : '';
            var desc = (savedParams[v] && savedParams[v].desc) ? savedParams[v].desc : '';
            
            var existingRow = container.find('.var-row[data-key="'+v+'"]');
            if (existingRow.length > 0) {
                 isRequired = existingRow.find('.var-req').is(':checked') ? 'checked' : '';
                 desc = existingRow.find('.var-desc').val();
            }
            currentHtml += `
            <li class="attribute-item flex items-center justify-between gap20" data-key="\${v}">
                <div class="body-title-2 email-template-name">
                    {{\${v}}}
                    <i class="icon-copy js-copy-settings" title="Получить переменную" data-copy="{{\${v}}}"></i>
                </div>
                <div class="body-text">
                    <input type="text" 
                           name="EmailTemplate[params][\${v}][desc]" 
                           value="\${desc}" 
                           class="form-control var-desc" 
                           placeholder="Описание переменной">
                </div>
                <div class="list-icon-function">
                    <label>
                        <input type="hidden" name="EmailTemplate[params][\${v}][required]" value="0">
                        <input type="checkbox" 
                               name="EmailTemplate[params][\${v}][required]" 
                               value="1" 
                               class="var-req" \${isRequired}> Обязательно
                    </label>
                </div>
            </li>`;
        });

        container.html(currentHtml);
    }
    
    setTimeout(parseVariables, 500);
});
JS;
$this->registerJs($js);
?>