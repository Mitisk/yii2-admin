<?php
/**
 * Поле одной настройки (общий рендер для всех разделов).
 *
 * @var yii\web\View $this
 * @var \Mitisk\Yii2Admin\models\Settings $setting
 * @var string $modelName
 * @var array $emailTemplates
 */

use yii\helpers\Html;
?>
<fieldset class="mb-10">
    <div class="body-title mb-10"><?= Html::encode($setting->label ?: $setting->attribute) ?>
        <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('<?= $modelName ?>', '<?= $setting->attribute ?>');"></i>
    </div>
    <?php
        switch ($setting->type) {
            case 'boolean':
                echo Html::dropDownList("Settings[{$setting->id}]", $setting->value, [
                    '0' => 'Нет',
                    '1' => 'Да',
                ], ['class' => 'select flex-grow tom-select']);
                break;
            case 'integer':
            case 'int':
            case 'float':
                echo Html::input('number', "Settings[{$setting->id}]", $setting->value, ['class' => 'form-control']);
                break;
            case 'mail_template':
                echo Html::dropDownList("Settings[{$setting->id}]", $setting->value, $emailTemplates, ['class' => 'select flex-grow tom-select']);
                break;
            case 'json':
            case 'textarea':
            case 'text':
                echo Html::textarea("Settings[{$setting->id}]", $setting->value);
                break;
            default:
                echo Html::textInput("Settings[{$setting->id}]", $setting->value, ['class' => 'form-control']);
        }
    ?>
    <?php if ($setting->description) : ?>
        <div class="body-text mb-24">
            <?= $setting->description ?>
        </div>
    <?php endif; ?>
</fieldset>
