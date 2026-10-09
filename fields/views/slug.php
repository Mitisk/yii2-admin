<?php
/** @var $field \Mitisk\Yii2Admin\fields\SlugField */
/** @var $model \Mitisk\Yii2Admin\core\models\AdminModel */
/** @var $fieldId string */
/** @var $formName string Имя формы целевой модели */

use yii\helpers\Html;
use yii\helpers\Json;

$sourceNames = Json::encode(['name', 'title', 'label']);
$formNameJs = Json::encode($formName);
?>
    <div class="form-group">
        <label class="body-title mb-10" for="<?= $fieldId ?>">
            <?= $field->label ?>
            <?php if ($field->required) { ?><span class="tf-color-1">*</span><?php } ?>
        </label>

        <?= Html::activeInput('text', $model->getModel(), $field->name, [
            'class' => $field->className,
            'placeholder' => $field->placeholder,
            'maxlength' => $field->maxlength,
            'id' => $fieldId,
            'required' => $field->required,
            'readonly' => $field->readonly,
            'autocomplete' => 'off',
            'data-slug' => '1',
        ]); ?>

        <div class="col-lg-7 invalid-feedback"></div>
    </div>

<?= $this->render('_help_block', ['field' => $field]) ?>

<?php
$this->registerJs(<<<JS
(function(){
    var slug = document.getElementById('{$fieldId}');
    if (!slug || slug.dataset.slugBound) { return; }
    slug.dataset.slugBound = '1';

    var formName = {$formNameJs};
    var sourceNames = {$sourceNames};

    // Транслитерация кириллицы
    var map = {
        'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z',
        'и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r',
        'с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','ш':'sh','щ':'sch',
        'ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'
    };

    function slugify(str) {
        str = (str || '').toLowerCase();
        var out = '';
        for (var i = 0; i < str.length; i++) {
            var ch = str[i];
            out += (map[ch] !== undefined) ? map[ch] : ch;
        }
        return out
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+\$/g, '')
            .replace(/-{2,}/g, '-');
    }

    // Пользователь правил slug вручную — перестаём автозаполнять
    var manual = slug.value.trim() !== '';
    slug.addEventListener('input', function(){ manual = true; });

    // Ищем поле-источник
    var source = null;
    for (var i = 0; i < sourceNames.length; i++) {
        var el = document.querySelector('[name="' + formName + '[' + sourceNames[i] + ']"]');
        if (el) { source = el; break; }
    }
    if (!source) { return; }

    source.addEventListener('input', function(){
        if (manual) { return; }
        slug.value = slugify(source.value);
    });
})();
JS
);
?>
