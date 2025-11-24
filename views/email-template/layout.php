<?php

use yii\helpers\Html;
use Mitisk\Yii2Admin\assets\AceAsset;

/* @var $this yii\web\View */
/* @var $content string */

$this->title = 'Общий макет писем (Layout)';
$this->params['breadcrumbs'][] = ['label' => 'Шаблоны писем', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Подключаем Ace
if (class_exists('Mitisk\Yii2Admin\assets\AceAsset')) {
    AceAsset::register($this);
}

// ID для контейнера редактора
$editorId = 'mail_layout_editor';
?>

    <div class="email-template-layout">

        <div class="block-warning w-full mb-24">
            <i class="icon-alert-octagon"></i>
            <div class="body-title-2 flex items-center gap10 flex-wrap">Используйте плейсхолдер <span class="block-pending">{{content}}</span> в том месте, где должен выводиться текст конкретного шаблона письма.</div>
        </div>

        <?= Html::beginForm(['layout'], 'post', ['id' => 'layout-form']) ?>

        <div class="row">
            <div class="col-md-6">

                <div id="<?= $editorId ?>__ace" class="ace-host" style="width:100%; height:600px; border:1px solid #e5e7eb; border-radius:12px;"></div>

                <?= Html::textarea('content', $content, ['id' => 'real-textarea', 'style' => 'display:none;']) ?>

                <div class="form-group" style="margin-top: 20px;">
                    <?= Html::submitButton('Сохранить макет', ['class' => 'tf-button w208']) ?>
                </div>
            </div>

            <div class="col-md-6">
                <div style="border: 1px solid #ddd; border-radius: 12px; height: 600px; overflow: hidden; background: #fff;">
                    <iframe id="preview-frame" style="width: 100%; height: 100%; border: 0;"></iframe>
                </div>
            </div>
        </div>

        <?= Html::endForm() ?>
    </div>

<?php
// Передаем контент в JS безопасным способом
$initialContent = json_encode($content);

$js = <<<JS
$(document).ready(function() {
    // 1. Инициализация Ace Editor
    var editor = ace.edit("{$editorId}__ace");
    editor.setTheme("ace/theme/chrome"); // Можно поменять тему
    editor.session.setMode("ace/mode/html");
    
    // Устанавливаем начальное значение
    editor.setValue({$initialContent}, -1); // -1 moves cursor to start

    var textarea = $('#real-textarea');
    var iframe = document.getElementById('preview-frame');

    // Функция обновления превью и скрытого поля
    function updateState() {
        var code = editor.getValue();
        
        // Обновляем скрытое поле для отправки формы
        textarea.val(code);

        // Обновляем превью
        // Заменяем {{content}} на рыбу для наглядности
        var previewHtml = code.replace(
            '{{content}}', 
            '<div style="padding: 20px; border: 2px dashed #ccc; background: #fafafa; text-align: center; color: #777;">' +
            '<h3>Пример заголовка письма</h3>' +
            '<p>Здесь будет отображаться содержимое конкретного шаблона.</p>' +
            '</div>'
        );

        var doc = iframe.contentWindow || iframe.contentDocument.document || iframe.contentDocument;
        if (doc.document) doc = doc.document;
        
        doc.open();
        doc.write(previewHtml);
        doc.close();
    }

    // Слушаем изменения в редакторе
    editor.getSession().on('change', function() {
        updateState();
    });

    // Первоначальный рендер
    updateState();

    // Дополнительная страховка перед сабмитом
    $('#layout-form').on('submit', function() {
        textarea.val(editor.getValue());
    });
});
JS;

$this->registerJs($js);
?>