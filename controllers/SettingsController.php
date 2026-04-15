<?php

/**
 * Файл контроллера раздела «Настройки сайта» админ-панели.
 *
 * PHP version 8.1
 *
 * @category Controller
 * @package  Mitisk\Yii2Admin\controllers
 * @author   Mitisk <akimkinpit@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/mitisk/yii2-admin
 */

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\EmailTemplate;
use Mitisk\Yii2Admin\models\SettingsBlock;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use Yii;
use yii\web\UploadedFile;
use yii\helpers\FileHelper;
use yii\db\Exception;
use Mitisk\Yii2Admin\models\File;
use Mitisk\Yii2Admin\models\Settings;

/**
 * Контроллер административного раздела «Настройки сайта».
 *
 * Отвечает за:
 *  - отображение и сохранение всех настроек (`actionIndex`), включая загрузку
 *    файлов и управление связанными записями {@see File};
 *  - создание новых настроек (`actionCreateSetting`) и разделов
 *    (`actionCreateSection`);
 *  - AJAX-редактирование заголовков/описаний разделов (`actionUpdateSectionName`);
 *  - сохранение API-ключа лицензии (`actionSaveApi`).
 *
 * Все AJAX-экшены возвращают JSON и требуют роли `superAdminRole` или `admin`.
 *
 * @category Controller
 * @package  Mitisk\Yii2Admin\controllers
 * @author   Mitisk <akimkinpit@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/mitisk/yii2-admin
 */
class SettingsController extends BaseController
{
    /**
     * Список типов настроек, допустимых при создании через UI.
     *
     * @var string[]
     */
    private const ALLOWED_SETTING_TYPES = [
        'string', 'text', 'textarea', 'integer', 'float',
        'boolean', 'json', 'file', 'mail_template',
    ];

    /**
     * Системные ключи разделов, которые нельзя перезаписать пользовательскими.
     *
     * @var string[]
     */
    private const RESERVED_SECTION_KEYS = [
        'GENERAL',
        'ADMIN',
        'HIDDEN',
        'Mitisk\\Yii2Admin\\models\\File',
    ];

    /**
     * Регистрирует фильтр доступа: только роли `superAdminRole` и `admin`.
     *
     * @return array<string, array<string, mixed>>
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['superAdminRole', 'admin']
                    ],
                ],
            ],
        ];
    }

    /**
     * Главный экшн: отображает страницу настроек и обрабатывает её сохранение.
     *
     * На GET собирает все настройки из БД, список моделей для заголовков табов,
     * метаданные разделов ({@see SettingsBlock}) и шаблоны писем, после чего
     * рендерит представление `index`.
     *
     * На POST пробегает по всем текущим настройкам и:
     *  - для типа `file` — удаляет/заменяет/сохраняет связанный {@see File}
     *    (с uniq-именем, пересозданием директории и обновлением alt-атрибута);
     *  - для остальных типов — сохраняет значение из `Settings[ID]`
     *    (массивы склеиваются через запятую).
     * Вся обработка обёрнута в транзакцию; при исключении откатывается и
     * во flash пишется ошибка.
     *
     * Query-параметр `modelName` используется только для подсветки активной
     * вкладки (см. представление), фильтрация набора настроек по нему больше
     * не выполняется.
     *
     * @return \yii\web\Response|string Ответ редиректа после POST или HTML страницы.
     */
    public function actionIndex(): \yii\web\Response|string
    {
        $modelName = Yii::$app->request->get('modelName');

        $settings = Settings::find()
            ->orderBy(['model_name' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $modelsNames = ArrayHelper::map(
            AdminModel::find()
                ->select(['name', 'model_class'])
                ->andWhere(['not', ['name' => null]])
                ->andWhere(['not', ['model_class' => null]])
                ->asArray()
                ->all(),
            'model_class',
            'name'
        );

        if (Yii::$app->request->isPost) {
            $postData = Yii::$app->request->post('Settings', []);
            $fu = Yii::$app->request->post('FileUploader', []);
            $tx = Yii::$app->db->beginTransaction();
            try {
                foreach ($settings as $setting) {
                    // Файловые поля
                    if ((string)$setting->type === 'file') {
                        $inputName = "Settings[{$setting->id}]";
                        $uploaded = UploadedFile::getInstanceByName($inputName); // новый файл, если пришёл

                        // Оставленные (существующие) файлы по данным FileUploader[ID]
                        $kept = ArrayHelper::getValue($fu, (string)$setting->id, []);
                        $keptIds = array_map('intval', array_keys((array)$kept));

                        // Обновление alt для существующих файлов (если он есть и не отключён в UI)
                        if (!empty($kept)) {
                            $altMap = [];
                            foreach ($kept as $fid => $row) {
                                if (array_key_exists('alt', $row)) {
                                    $altMap[(int)$fid] = (string)$row['alt'];
                                }
                            }
                            if ($altMap) {
                                // Массовое обновление alt_attribute
                                $case = [];
                                $params = [];
                                foreach ($altMap as $fid => $alt) {
                                    $case[] = "WHEN id = {$fid} THEN :alt_{$fid}";
                                    $params[":alt_{$fid}"] = $alt;
                                }
                                $caseSql = 'CASE ' . implode(' ', $case) . ' ELSE alt_attribute END';
                                File::updateAll(['alt_attribute' => new \yii\db\Expression($caseSql)], ['id' => array_keys($altMap)], $params);
                            }
                        }

                        // 1) Если загружен новый файл — заменяем старый
                        if ($uploaded instanceof UploadedFile) {
                            // удалить старый, если был
                            if (!empty($setting->value) && ($old = File::findOne((int)$setting->value))) {
                                $old->delete();
                            }

                            // гарантируем папку web/admin
                            $webSubdir = '/uploads/admin';
                            $fsDir = Yii::getAlias('@webroot') . $webSubdir;
                            FileHelper::createDirectory($fsDir, 0775, true);

                            // безопасное имя
                            $base = pathinfo($uploaded->name, PATHINFO_FILENAME);
                            $ext = strtolower($uploaded->getExtension());
                            $safeBase = preg_replace('~[^a-z0-9_-]+~i', '_', $base);
                            $uniq = date('Ymd_His') . '_' . Yii::$app->security->generateRandomString(6);
                            $newName = "{$safeBase}_{$uniq}.{$ext}";
                            $fsPath = $fsDir . DIRECTORY_SEPARATOR . $newName;
                            $publicPath = '/web' . $webSubdir . '/' . $newName; // пример: /web/admin/xxx.png

                            if (!$uploaded->saveAs($fsPath, false)) {
                                throw new Exception('Не удалось сохранить файл');
                            }

                            // получить alt из temp (если он есть)
                            $tempRows = ArrayHelper::getValue($fu, "temp.{$setting->id}", []);
                            $firstTemp = is_array($tempRows) ? reset($tempRows) : null;
                            $alt = is_array($firstTemp) && array_key_exists('alt', $firstTemp) ? (string)$firstTemp['alt'] : null;

                            // запись в таблицу file
                            $f = new File();
                            $f->filename = $uploaded->name;
                            $f->file_size = (int)filesize($fsPath);
                            $f->mime_type = FileHelper::getMimeType($fsPath) ?: $uploaded->type;
                            $f->path = $publicPath; // важно: публичный путь из web/, например /admin/...
                            $f->uploaded_at = date('Y-m-d H:i:s');
                            $f->class_name = 'Settings';
                            $f->item_id = (int)$setting->id;
                            $f->field_name = 'value';
                            if ($alt !== null) {
                                $f->alt_attribute = $alt;
                            }
                            if (!$f->save()) {
                                throw new Exception('Не удалось создать запись file');
                            }

                            // связать с настройкой
                            $setting->value = (string)$f->id;
                            $setting->updated_at = time();
                            $setting->save(false, ['value', 'updated_at']);

                            continue;
                        }

                        // 2) Нового файла нет: если существующий не «оставлен» — удалить
                        $existingId = (int)($setting->value ?? 0);
                        $isKept = $existingId > 0 && in_array($existingId, $keptIds, true);
                        if ($existingId && !$isKept) {
                            if ($old = File::findOne($existingId)) {
                                $old->delete();
                            }
                            $setting->value = '';
                            $setting->updated_at = time();
                            $setting->save(false, ['value', 'updated_at']);
                        }

                        // 3) Существующий оставлен и нового нет — ничего не делаем
                        continue;
                    }

                    // Остальные типы — как было
                    if (isset($postData[$setting->id])) {
                        if (is_array($postData[$setting->id])) {
                            $prepared = array_filter($postData[$setting->id], static fn($v) => (string)$v !== '');
                            $postData[$setting->id] = implode(',', $prepared);
                        }
                        $setting->value = $postData[$setting->id];
                        $setting->updated_at = time();
                        $setting->save(false, ['value', 'updated_at']);
                    }
                }

                $tx->commit();
                Yii::$app->session->setFlash('success', 'Настройки успешно сохранены!');
                return $this->refresh();
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', 'Ошибка сохранения настроек: ' . $e->getMessage());
            }
        }

        $settingsBlockModel = SettingsBlock::find()
            ->select(['model_name', 'label', 'description'])
            ->asArray()
            ->indexBy('model_name')
            ->all();

        $settingsBlock = array_map(function ($r) {
            return ['label' => $r['label'], 'description' => $r['description']];
        }, $settingsBlockModel);

        $emailTemplates = [null => '---'] + ArrayHelper::map(EmailTemplate::find()
            ->select(['slug', 'name'])
            ->asArray()
            ->all(), 'slug', 'name');

        return $this->render('index', [
            'settings'      => $settings,
            'modelsNames'   => $modelsNames,
            'modelName'     => $modelName,
            'settingsBlock' => $settingsBlock,
            'emailTemplates' => $emailTemplates,
        ]);
    }

    /**
     * AJAX: обновляет заголовок или описание раздела настроек.
     *
     * POST-параметры:
     *  - `key`   (string) — ключ раздела (`model_name` в {@see SettingsBlock});
     *  - `value` (string) — новое значение (непустое);
     *  - `type`  (string) — `title` для поля `label`, иначе меняется `description`.
     *
     * Если раздела с таким ключом ещё нет — создаётся новый.
     *
     * @return array{ok: bool, value?: string, error?: string} JSON-ответ.
     */
    public function actionUpdateSectionName(): array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $key = Yii::$app->request->post('key');
        $value = trim((string)Yii::$app->request->post('value'));
        $type = Yii::$app->request->post('type');

        if ($key === null || $value === '') {
            return ['ok' => false, 'error' => 'Invalid data'];
        }

        /** @var SettingsBlock $model */
        $model = SettingsBlock::find()->where(['model_name' => $key])->one();
        if (!$model) {
            $model = new SettingsBlock();
            $model->model_name = $key;
        }

        if ($type === 'title') {
            $model->label = $value;
        } else {
            $model->description = $value;
        }

        $model->save(false);

        return ['ok' => true, 'value' => $value];
    }

    /**
     * AJAX: создаёт новую настройку внутри указанного блока (`model_name`).
     *
     * POST-параметры:
     *  - `model_name`  (string) — ключ раздела, обязателен;
     *  - `attribute`   (string) — имя атрибута, `^[A-Za-z_][A-Za-z0-9_]*$`;
     *  - `label`       (string) — человекочитаемое название;
     *  - `type`        (string) — один из {@see self::ALLOWED_SETTING_TYPES};
     *  - `value`       (string) — стартовое значение (для `boolean` — `0`/`1`);
     *  - `description` (string) — подсказка для администратора.
     *
     * После успешного создания сбрасывает in-memory кеш {@see Settings::clearCache()}.
     *
     * @return array{ok: bool, id?: int, error?: string} JSON-ответ.
     */
    public function actionCreateSetting(): array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $modelName   = trim((string)Yii::$app->request->post('model_name', ''));
        $attribute   = trim((string)Yii::$app->request->post('attribute', ''));
        $label       = trim((string)Yii::$app->request->post('label', ''));
        $type        = (string)Yii::$app->request->post('type', 'string');
        $value       = (string)Yii::$app->request->post('value', '');
        $description = trim((string)Yii::$app->request->post('description', ''));

        if ($modelName === '' || $attribute === '') {
            return ['ok' => false, 'error' => 'Поля «Модель» и «Атрибут» обязательны.'];
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $attribute)) {
            return [
                'ok' => false,
                'error' => 'Атрибут должен содержать только латиницу, цифры и подчёркивания и не начинаться с цифры.',
            ];
        }

        if (!in_array($type, self::ALLOWED_SETTING_TYPES, true)) {
            return ['ok' => false, 'error' => 'Недопустимый тип настройки.'];
        }

        if (Settings::find()->where(['model_name' => $modelName, 'attribute' => $attribute])->exists()) {
            return ['ok' => false, 'error' => 'Настройка с таким атрибутом уже существует в этом блоке.'];
        }

        $s = new Settings();
        $s->model_name  = $modelName;
        $s->attribute   = $attribute;
        $s->label       = $label !== '' ? $label : null;
        $s->type        = $type;
        $s->value       = $type === 'boolean' ? ($value === '1' ? '1' : '0') : $value;
        $s->description = $description !== '' ? $description : null;
        $s->updated_at  = time();

        if (!$s->save()) {
            return ['ok' => false, 'error' => implode('; ', $s->getFirstErrors())];
        }
        Settings::clearCache($modelName);

        return ['ok' => true, 'id' => $s->id];
    }

    /**
     * AJAX: создаёт новый раздел (блок) настроек.
     *
     * POST-параметры:
     *  - `key`         (string) — уникальный ключ раздела,
     *                  `^[A-Za-z_][A-Za-z0-9_\\]*$` (бэкслэш допускается для FQCN моделей);
     *  - `label`       (string) — название раздела, обязательное;
     *  - `description` (string) — описание, опционально.
     *
     * Запрещены ключи из {@see self::RESERVED_SECTION_KEYS}.
     *
     * @return array{ok: bool, key?: string, error?: string} JSON-ответ.
     */
    public function actionCreateSection(): array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $key         = trim((string)Yii::$app->request->post('key', ''));
        $label       = trim((string)Yii::$app->request->post('label', ''));
        $description = trim((string)Yii::$app->request->post('description', ''));

        if ($key === '' || $label === '') {
            return ['ok' => false, 'error' => 'Поля «Ключ» и «Название» обязательны.'];
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $key)) {
            return [
                'ok' => false,
                'error' => 'Ключ может содержать латиницу, цифры, «_» и «\\» (для классов моделей).',
            ];
        }

        if (in_array($key, self::RESERVED_SECTION_KEYS, true)) {
            return ['ok' => false, 'error' => 'Этот ключ зарезервирован системой.'];
        }

        if (SettingsBlock::find()->where(['model_name' => $key])->exists()) {
            return ['ok' => false, 'error' => 'Раздел с таким ключом уже существует.'];
        }

        $block = new SettingsBlock();
        $block->model_name  = $key;
        $block->label       = $label;
        $block->description = $description !== '' ? $description : null;

        if (!$block->save()) {
            return ['ok' => false, 'error' => implode('; ', $block->getFirstErrors())];
        }

        return ['ok' => true, 'key' => $key];
    }

    /**
     * AJAX: сохраняет API-ключ лицензии в раздел `GENERAL` → `api_key`.
     *
     * Принимает JSON/form-encoded тело с полем `api_key`. Пустое значение
     * приводит к ответу `{success: false}` без каких-либо изменений.
     *
     * @return array{success: bool} JSON-ответ.
     */
    public function actionSaveApi(): array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $data = Yii::$app->request->getBodyParams();
        $apiKey = $data['api_key'] ?? null;

        if ($apiKey && Yii::$app->settings->set('GENERAL', 'api_key', $apiKey)) {
            return ['success' => true];
        }

        return ['success' => false];
    }
}
