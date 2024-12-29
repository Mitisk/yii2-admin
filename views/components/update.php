<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;

/* @var $model Mitisk\Yii2Admin\models\AdminModel */
/* @var $requiredColumns array */
/* @var $addedAttributes array */
/* @var $modelInstance yii\db\ActiveRecord */
/* @var $columns array */
/* @var $allColumns array */
/* @var $this yii\web\View */

$this->title = 'Редактирование компонента';

$this->params['breadcrumbs'][] = ['label' => 'Компоненты', 'url' => ['index'] ];
$this->params['breadcrumbs'][] = $this->title;
?>

<?php $form = ActiveForm::begin([
    'id' => 'login-form',
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{error}",
        'labelOptions' => ['class' => 'body-title mb-10'],
        'inputOptions' => ['class' => ''],
        'errorOptions' => ['class' => 'col-lg-7 invalid-feedback'],
    ],
    'options' => ['class' => 'flex flex-column gap24']
]) ?>
    <div class="wg-box mb-20">
        <fieldset class="name">
            <?= $form->field($model, 'name')->textInput(['maxlength' => 255])->label('Название <span class="tf-color-1">*</span>') ?>
        </fieldset>
        <fieldset class="name">
            <label class="body-title mb-10" for="adminmodel-alias">Алиас</label>
            <div class="input-group">
                <span class="input-group-text" style="font-size: 14px;padding-right: 0;">https://<?= $_SERVER['HTTP_HOST'] ?>/admin/</span>

                <?= Html::activeInput('text', $model, 'alias', ['maxlength' => 255, 'class' => 'form-control', 'style' => 'padding-left: 2px'])?>
                <div class="box-coppy">
                    <div class="coppy-content" style="display: none">https://<?= $_SERVER['HTTP_HOST'] ?>/admin/<?= $model->alias ?></div>
                <i class="icon-copy button-coppy"></i>
                </div>
            </div>
        </fieldset>

        <div class="flex gap10 mb-24">
            <?= Html::ActiveCheckbox($model, 'in_menu', ['class' => 'total-checkbox']) ?>
            <label for="adminmodel-in_menu" class="body-text">Добавить в меню слева. <a href="/admin/menu" target="_blank" class="tf-color">Редактировать в меню</a></label>
        </div>
        <fieldset class="name">
            <?= $form->field($model, 'model_class')->textInput(['maxlength' => 255]) ?>
        </fieldset>
        <div class="block-warning type-main w-full">
            <i class="icon-alert-octagon"></i>
            <div class="body-title-2">Пример: app\models\ApiKey.</div>
        </div>
    </div>

    <?php if($model->model_class): ?>

    <div class="wg-box mb-20">
        <?php if($allColumns): ?>
            <h4>Столбцы в списке</h4>
            <div class="list-box-value mb-10 list-draggable-container">

                <?php foreach($model->list as $key => $column): ?>

                    <?= $this->render("_list_item", [
                        'model' => $model,
                        'column' => $key,
                        'requiredColumns' => $requiredColumns,
                        'name' => ArrayHelper::getValue($column, 'name'),
                        'description' => ArrayHelper::getValue($column, 'description'),
                    ]) ?>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>
        <?= $form->field($model, 'data')->hiddenInput()->label(false) ?>
    </div>
    <div class="wg-box mb-20">
            <?php if($columns): ?>
                <h4>Настройка полей</h4>
                <div class="list-box-value mb-10">

                    <?php foreach($columns as $column): ?>
                    <div class="box-value-item" style="gap: 10px;justify-content: left;">
                        <input class="total-checkbox js-click-to-attr" type="checkbox"
                               data-type="<?= \Mitisk\Yii2Admin\fields\FieldsHelper::getFieldsTypeByName($column) ?>"
                               data-name="<?= $column ?>"
                               data-label="<?= $modelInstance->getAttributeLabel($column) ?>"
                               data-required="<?= in_array($column, $requiredColumns) ? 'true' : 'false' ?>"
                        <?= in_array($column, $addedAttributes) ? 'checked disabled' : ''?>>
                        <div class="body-text">
                            <?= $modelInstance->getAttributeLabel($column) ?><?= in_array($column, $requiredColumns) ? ' <span class="tf-color-1">*</span>' : '' ?>
                            <span class="block-pending"><?= $column ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="build-wrap"></div>
            <?php endif; ?>
            <?= $form->field($model, 'data')->hiddenInput()->label(false) ?>
    </div>

    <?php endif; ?>

        <div class="bot">
            <div></div>
            <button class="tf-button w208" type="submit"><?= ($model->model_class) ? 'Сохранить' : 'Продолжить'?></button>
        </div>

    <?php ActiveForm::end() ?>


<?php
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('/web/js/form-builder.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('/web/js/form-render.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('/web/js/drag-arrange/drag-arrange.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);

$this->registerJs("

    $('.list-draggable').arrangeable({dragSelector: '.drag-area'});

    const fbTemplate = document.getElementById('build-wrap');
    if(fbTemplate) {
        var formBuilder = $(fbTemplate).formBuilder({
            i18n: {
                locale: 'ru-RU',
                location: '/web/lang/'
            },
            
            formData: '" . ($model->data) . "',
            dataType: 'json',
           

            // additional form action buttons- save, data, clear
            actionButtons: [],

            // enables/disables stage sorting
            allowStageSort: true,

            // append/prepend non-editable content to the form.
            append: false,
            prepend: false,

            // control order
            controlOrder: [
                'text',
                'textarea',
                'select',
                'checkbox-group',
                'checkbox',
                'date',
                'file',

                'autocomplete',
                'button',


                'hidden',
                'number',

                'radio-group',

                'header',
                'paragraph',

            ],

            // or left
            controlPosition: 'right',

            // or 'xml'
            dataType: 'json',

            // default fields
            defaultFields: [],

            // save, data, clear
            disabledActionButtons: ['save', 'data', 'clear'],

            // disabled attributes
            disabledAttrs: [],

            // disabled buttons
            disabledFieldButtons: {},

            // disabled subtypes
            disabledSubtypes: {},

            // disabled fields
            disableFields: [],

            // disables html in field labels
            disableHTMLLabels: false,

            // disables embedded bootstrap classes
            // setting to true will disable all styles
            disableInjectedStyle: 'bootstrap',

            // removes the injected style
            disableInjectedStyle: false,

            // opens the edit panel on added field
            editOnAdd: false,

            // adds custom control configs
            fields: [],

            // warns user if before the remove a field from the stage
            fieldRemoveWarn: false,

            // DOM node or selector
            fieldEditContainer: null,

            // add groups of fields at a time
            inputSets: [],

            // custom notifications
            notify: {
                error: console.error,
                success: console.log,
                warning: console.warn,
            },

            // prevent clearAll from remove default fields
            persistDefaultFields: false,

            // callbakcs
            //onAddField: (fieldData, fieldId) => fieldData,
            onAddField: function(editPanel) {
                checkFields();
            },
            onAddOption: () => null,
            onClearAll: function(editPanel) {
                checkFields();
            },
            onCloseFieldEdit: function(editPanel) {
                checkFields();
            },
            onOpenFieldEdit: () => null,
            onSave: function(evt, formData) {
                $('#adminmodel-data').val((formData));
            },

            // replaces an existing field by id.
            replaceFields: [],

            // user roles
            roles: {
                1: 'Администратор',
            },

            // smoothly scrolls to a field when its added to the stage
            scrollToFieldOnAdd: true,

            // shows action buttons
            showActionButtons: true,

            // sortable controls
            sortableControls: false,

            // sticky controls
            stickyControls: {
                enable: true,
                offset: {
                    top: 5,
                    bottom: 'auto',
                    right: 'auto',
                },
            },

            // defines new types to be used with field base types such as button and input
            subtypes: {},

            // defines a custom output for new or existing fields.
            templates: {},

            // defines custom attributes for field types
            typeUserAttrs: {},

            // disabled attributes for specific field types
            typeUserDisabledAttrs: {},

            // adds functionality to existing and custom attributes using onclone and onadd events. Events return JavaScript DOM elements.
            typeUserEvents: {},
            
            enableEnhancedBootstrapGrid: true,
        });
    }
        
        function checkFields() {
            var fields = formBuilder.formData;
            
            if(fields !== false) {
                $('#adminmodel-data').val(fields);
                
                if(fields.length > 0) {
                    fields = JSON.parse(fields);
                        
                    $('.js-click-to-attr').removeAttr('checked').removeAttr('disabled');
                    
                    $.each(fields, function(i, item) {
                        if(item.name) {
                            $('.js-click-to-attr[data-name=\"' + item.name + '\"]').attr('checked', 'checked').attr('disabled', 'disabled');
                        }
                    });
                }
            }
        }

        $(document).on('click', '.js-click-to-attr', function(e) {
           
            var field = {
                type: $(this).data('type'),
                className: 'form-control',
                name: $(this).data('name'),
                label: $(this).data('label'),
                required: $(this).data('required'),
            };
            if(!$(this).attr('disabled')) {
                formBuilder.actions.addField(field);
            }
            
            $(this).attr('disabled', 'disabled');
            checkFields();
        });
        
        
        $(document).on('click', '.js-list-actions', function(e) {
            $(this).toggleClass('active');
            $(this).find('input').val($(this).find('input').val() == 1 ? 0 : 1);
        });
");
?>