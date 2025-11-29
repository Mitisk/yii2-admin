<?php
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

class SettingsController extends BaseController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
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

    public function actionIndex()
    {
        $modelName = Yii::$app->request->get('modelName');

        $settingsQuery = Settings::find()->orderBy(['model_name' => SORT_ASC, 'id' => SORT_ASC]);
        if ($modelName) {
            $settingsQuery->andWhere(['model_name' => $modelName]);
        }
        $settings = $settingsQuery->all();

        $modelsNames = AdminModel::find()->select(['name', 'model_class'])
            ->andWhere(['not', ['name' => null]])
            ->andWhere(['not', ['model_class' => null]]);
        if ($modelName) {
            $modelsNames->andWhere(['model_class' => $modelName]);
        }
        $modelsNames = ArrayHelper::map($modelsNames->asArray()->all(), 'model_class', 'name');

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
     * Action для сохранения названия раздела
     * @return array
     */
    public function actionUpdateSectionName() : array
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
     * Action для сохранения API ключа
     * @return false[]|true[]
     */
    public function actionSaveApi() : array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $data = Yii::$app->request->getBodyParams();
        $apiKey = $data['api_key'] ?? null;

        if ($apiKey) {
            if (Yii::$app->settings->set('GENERAL', 'api_key', $apiKey)) {
                //Yii::$app->session->setFlash('success', 'API ключ успешно получен!');
                return ['success' => true];
            }
        }
        return ['success' => false];
    }
}
