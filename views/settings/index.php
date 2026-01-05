<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/** @var yii\web\View $this */
/** @var \Mitisk\Yii2Admin\models\Settings[] $settings */
/** @var array $modelsNames */
/** @var array $emailTemplates Массив шаблонов писем */
/** @var string $modelName */
/** @var array $settingsBlock Имена и описания блоков настроек */

$this->params['breadcrumbs'][] = ['label' => 'Настройки сайта'];
$this->title = $this->params['pageHeaderText'] = 'Настройки сайта';

\Mitisk\Yii2Admin\assets\FieldFileAsset::register($this);
\Mitisk\Yii2Admin\assets\SettingsAsset::register($this);
?>

<?php $form = ActiveForm::begin(['options' => ['class' => 'form-setting form-style-2', 'enctype' => 'multipart/form-data']]); ?>

<?php if (!$modelName) : ?>
<div class="wg-box">
    <div class="left js-change-header">
        <h5 class="mb-4"><?= ArrayHelper::getValue($settingsBlock, 'GENERAL.label', 'Основные') ?></h5>
        <input type="text" name="names[GENERAL]" value="<?= ArrayHelper::getValue($settingsBlock, 'GENERAL.label', 'Основные')?>" tabindex="2" style="display: none">
        <div class="body-text"><?= ArrayHelper::getValue($settingsBlock, 'GENERAL.description', 'Это основные настройки')?></div>
        <textarea name="description[GENERAL]" style="display: none"><?= ArrayHelper::getValue($settingsBlock, 'GENERAL.description', 'Это основные настройки')?></textarea>
    </div>

    <div class="right flex-grow">

        <?php if (!Yii::$app->settings->get('GENERAL', 'api_key')) : ?>

            <div class="block-warning w-full mb-24 js-error-api">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Ваша лицензия неактивна! Получите API ключ ниже.</div>
            </div>

            <div class="block-warning type-main w-full mb-24 js-success hidden">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Лицензия активирована.</div>
            </div>

            <div class="flex flex-wrap gap10 mb-50 js-error-api">
                <a href="#" class="tf-button js-get-api-key">Запросить API ключ</a>
            </div>

        <?php else: ?>

            <div class="block-warning type-main w-full mb-24">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Лицензия активирована.</div>
            </div>

        <?php endif; ?>

        <fieldset class="mb-10">
            <div class="body-title mb-10">Название сайта
                <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('GENERAL', 'site_name');"></i>
            </div>
            <?php
            echo Html::textInput("Settings[4]", Yii::$app->settings->get('GENERAL', 'site_name'), ['class' => 'form-control']);
            ?>
        </fieldset>

        <?php
        $emails = Yii::$app->settings->get('GENERAL', 'admin_email', []);
        if ($emails) {
            $emails = explode(',', $emails);
        }
        ?>
        <div class="body-title mb-10">Email администратора <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('GENERAL', 'admin_email');"></i></div>
        <?php if ($emails) :?>
            <?php foreach ($emails as $email) :?>
                <fieldset class="email mb-10 add-more-right js-add-email">
                    <input class="flex-grow" type="email" placeholder="@" name="Settings[2][]" tabindex="0" value="<?= $email ?>" aria-required="true">
                    <a href="#" class="tf-button add-more js-add-more" style="display:none;">Добавить <i class="icon-plus"></i></a>
                    <a href="#" class="tf-button add-more remove-email">Удалить <i class="icon-trash"></i></a>
                </fieldset>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!$emails || count($emails) < 5) : ?>
            <fieldset class="email mb-10 add-more-right js-add-email">
                <input class="flex-grow" type="email" placeholder="@" name="Settings[2][]" tabindex="0" value="" aria-required="true">
                <a href="#" class="tf-button add-more js-add-more">Добавить <i class="icon-plus"></i></a>
                <a href="#" class="tf-button add-more remove-email" style="display:none;">Удалить <i class="icon-trash"></i></a>
            </fieldset>
        <?php endif; ?>
        <?php if($emails && count($emails) >= 5): ?>
            <div class="block-warning type-main w-full mb-24">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Добавлено максимальное количество Email адресов</div>
            </div>
        <?php else: ?>
            <div class="block-warning type-main w-full mb-24">
                <i class="icon-alert-octagon"></i>
                <div class="body-title-2">Добавьте до 5 штук</div>
            </div>
        <?php endif; ?>

        <?php
        $utc = Yii::$app->settings->get('GENERAL', 'timezone');
        ?>
        <fieldset class="timezone mb-24">
            <div class="body-title mb-10">
                Временная зона
                <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('GENERAL', 'timezone');"></i>
            </div>
            <div class="select flex-grow">
                <select id="timezone-select" name="Settings[3]" placeholder="Выберите зону...">
                </select>
            </div>
            <div class="body-text mb-24">
                Выберите часовой пояс сайта относительно времени UTC.
                Время UTC: <b><?= (new DateTime('now', new DateTimeZone('UTC')))->format('H:i') ?></b>
            </div>
        </fieldset>

        <script>
            // Проверка на существование переменной, чтобы не сломать JS
            var selectedTimezone = <?= isset($utc) && $utc ? json_encode($utc) : 'null' ?>;
        </script>
    </div>

</div>
    <div class="wg-box">
        <div class="left js-change-header">
            <h5 class="mb-4"><?= ArrayHelper::getValue($settingsBlock, 'ADMIN.label', 'Панель администратора') ?></h5>
            <input type="text" name="names[ADMIN]" value="<?= ArrayHelper::getValue($settingsBlock, 'ADMIN.label', 'Панель администратора')?>" tabindex="2" style="display: none">
            <div class="body-text"><?= ArrayHelper::getValue($settingsBlock, 'ADMIN.description', 'Это настройки панели администратора')?></div>
            <textarea name="description[ADMIN]" style="display: none"><?= ArrayHelper::getValue($settingsBlock, 'ADMIN.description', 'Это настройки панели администратора')?></textarea>
        </div>

        <div class="right flex-grow">

            <fieldset class="mb-10">
                <div class="body-title mb-10">Логотип (154х52 px)
                    <i class="icon-copy js-copy-settings" title="Получить настройку" data-copy="\Yii::$app->settings->get('ADMIN', 'logo');"></i>
                </div>

                <?php
                /** @var \Mitisk\Yii2Admin\models\Settings $fileSetting */
                $fileSetting = Yii::$app->settings->get('ADMIN', 'logo', getOnlyValue: false);
                $data = [];
                if ($fileSetting?->file) {
                    $data = [$fileSetting?->file->generateFileUploaderData('5')];
                }

                $filesJson = Json::encode($data); ?>

                <input type="file" class="fileuploader-single fileuploader-without-alt"
                       name="Settings[5]"
                       data-crop-width="154"
                       data-crop-height="52"
                       data-fileuploader-files='<?= $filesJson ?>'>

            </fieldset>

        </div>

    </div>
<?php endif; ?>

<?php
if ($settings) {
    // Extract file settings
    $fileSettings = [];
    foreach ($settings as $setting) {
        if ($setting->model_name === 'Mitisk\Yii2Admin\models\File') {
            $fileSettings[] = $setting;
        }
    }

    if (!empty($fileSettings)) {
         echo $this->render('_file_settings', ['settings' => $fileSettings]);
    }

    $groupedSettings = [];
    foreach ($settings as $setting) {
        if (in_array($setting->model_name, ['GENERAL', 'ADMIN', 'HIDDEN', 'Mitisk\Yii2Admin\models\File'])) continue;
        $groupedSettings[$setting->model_name][] = $setting;
    }

    foreach ($groupedSettings as $modelName => $settingsGroup) :
        echo  $this->render('_block', [
            'modelsNames' => $modelsNames,
            'modelName' => $modelName,
            'settings' => $settingsGroup,
            'settingsBlock' => $settingsBlock,
            'emailTemplates' => $emailTemplates,
        ]);
    endforeach;
}
?>

<?php if ($modelName) : ?>
    <div class="wg-box">

            <div class="body-text mb-24">Для добавления новой настройки используйте: </div>
            <div class="block-warning type-main w-full mb-24">
                <div class="body-title-2">\Yii::$app->settings->set('<?= $modelName ?>', 'attribute', $value, 'string');
                    <i class="icon-copy js-copy-settings" style="font-size: unset" title="Получить настройку" data-copy="\Yii::$app->settings->set('<?= $modelName ?>', 'attribute', $value, 'string');"></i>
                </div>
            </div>
    </div>

<?php endif; ?>

<div class="bot">
    <div></div>
    <button class="tf-button w208" type="submit">Сохранить</button>
</div>

<?php ActiveForm::end(); ?>