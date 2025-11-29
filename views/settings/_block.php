<?php
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var \Mitisk\Yii2Admin\models\Settings[] $settings */
/** @var string $modelName Название модели */
/** @var array $emailTemplates Массив шаблонов писем */
/** @var array $modelsNames */
/** @var array $settingsBlock Имена и описания блоков настроек */

$title = \yii\helpers\ArrayHelper::getValue($modelsNames, $modelName, $modelName);
if (ArrayHelper::getValue($settingsBlock, $modelName . '.label')) {
    $title = ArrayHelper::getValue($settingsBlock, $modelName . '.label');
}
$description = ArrayHelper::getValue($settingsBlock, $modelName . '.description');
if (!$description) {
    $description = '<i class="icon-edit"></i>';
}
?>
<div class="wg-box">
    <div class="left js-change-header">
        <h5 class="mb-4"><?= $title ?></h5>
        <input type="text" name="names[<?= $modelName ?>]" value="<?= $title ?>" tabindex="2" style="display: none">
        <div class="body-text"><?= $description ?></div>
        <textarea name="description[<?= $modelName ?>]" style="display: none"><?= $description ?></textarea>
    </div>

    <div class="right flex-grow">
        <?php foreach ($settings as $setting): ?>
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
        <?php endforeach; ?>
    </div>
</div>
