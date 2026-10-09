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
            <?= $this->render('_field', ['setting' => $setting, 'modelName' => $modelName, 'emailTemplates' => $emailTemplates]) ?>
        <?php endforeach; ?>

        <div class="add-setting-row">
            <button type="button"
                    class="add-setting-btn js-add-setting"
                    data-model="<?= Html::encode($modelName) ?>">
                <span class="add-setting-btn__plus" aria-hidden="true">+</span>
                <span class="add-setting-btn__text">
                    <span class="add-setting-btn__title">Добавить настройку</span>
                    <span class="add-setting-btn__sub">Создаст новый параметр в блоке «<?= Html::encode($title) ?>»</span>
                </span>
            </button>
        </div>
    </div>
</div>
