<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var \Mitisk\Yii2Admin\models\Settings[] $settings */
/** @var string $modelName */
/** @var array $modelsNames */
?>
<div class="wg-box">
    <div class="left">
        <h5 class="mb-4"><?= \yii\helpers\ArrayHelper::getValue($modelsNames, $modelName, $modelName)?></h5>
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
                            ], ['class' => 'select flex-grow']);
                            break;
                        case 'integer':
                        case 'int':
                        case 'float':
                            echo Html::input('number', "Settings[{$setting->id}]", $setting->value, ['class' => 'form-control']);
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
