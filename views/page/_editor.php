<?php

/**
 * Редактор текста страницы — единственное место, где выбран Trumbowyg.
 * Замена редактора = замена этого файла и page.js.
 */

/** @var yii\web\View $this */
/** @var Mitisk\Yii2Admin\models\forms\PageForm $form */
/** @var yii\widgets\ActiveForm $af */

use yii\helpers\Html;

$id = Html::getInputId($form, 'body');
?>
<div class="page-editor" data-page-editor>
    <div class="body-title mb-10">Текст</div>
    <div class="flex gap10 mb-10 page-editor-tabs">
        <button type="button" class="tf-button style-2 is-active" data-editor-tab="visual">Визуально</button>
        <button type="button" class="tf-button style-2" data-editor-tab="html">HTML</button>
    </div>
    <div data-editor-pane="visual">
        <?= $af->field($form, 'body', ['template' => "{input}\n{error}"])->textarea(['rows' => 18, 'id' => $id, 'data-page-body' => '1']) ?>
    </div>
    <div data-editor-pane="html" style="display:none">
        <textarea id="<?= $id ?>-html" rows="18" data-ace-mode="html" data-page-body-html="1"></textarea>
        <div id="<?= $id ?>-html__ace" class="ace-host" style="width:100%;height:420px;border:1px solid #e5e7eb;border-radius:6px"></div>
    </div>
</div>
