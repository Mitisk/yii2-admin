<?php
namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\SettingsBlock;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use Yii;
use Mitisk\Yii2Admin\models\Settings;

class SettingsController extends Controller
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
        /** @var string $modelName Отобразить настройку для конкретной модели */
        $modelName = Yii::$app->request->get('modelName');

        $settings = Settings::find()
            ->orderBy(['model_name' => SORT_ASC, 'id' => SORT_ASC]);

        if ($modelName) {
            $settings->andWhere(['model_name' => $modelName]);
        }

        $settings = $settings->all();

        $modelsNames = AdminModel::find()->select(['name', 'model_class'])
            ->andWhere(['not', ['name' => null]])
            ->andWhere(['not', ['model_class' => null]]);

        if ($modelName) {
            $modelsNames->andWhere(['model_class' => $modelName]);
        }

        $modelsNames = $modelsNames->asArray()->all();

        $modelsNames = ArrayHelper::map($modelsNames, 'model_class', 'name');

        if (Yii::$app->request->isPost) {
            $postData = Yii::$app->request->post('Settings', []);
            foreach ($settings as $setting) {
                if (isset($postData[$setting->id])) {
                    if (is_array($postData[$setting->id])) {
                        $preparedData = [];
                        foreach ($postData[$setting->id] as $item) {
                            if ($item) {
                                $preparedData[] = $item;
                            }
                        }
                        $postData[$setting->id] = implode(',', $preparedData);
                    }
                    $setting->value = $postData[$setting->id];
                    $setting->updated_at = time();
                    $setting->save(false, ['value', 'updated_at']);
                }
            }
            Yii::$app->session->setFlash('success', 'Настройки успешно сохранены!');
            return $this->refresh();
        }

        $settingsBlockModel = SettingsBlock::find()
            ->select(['model_name', 'label', 'description'])
            ->asArray()
            ->indexBy('model_name')
            ->all();

        $settingsBlock = array_map(function($r){
            return ['label' => $r['label'], 'description' => $r['description']];
        }, $settingsBlockModel);

        return $this->render('index', [
            'settings' => $settings,
            'modelsNames' => $modelsNames,
            'modelName' => $modelName,
            'settingsBlock' => $settingsBlock,
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
