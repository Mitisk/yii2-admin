<?php
namespace Mitisk\Yii2Admin\core\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\core\models\AdminModel;
use Yii;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

class AdminController extends BaseController
{
    /** @var string ClassName of model */
    protected $_modelName;

    /** @var string Шаблон листинга */
    public $actionIndexTemplate = 'index';

    /** @var string Шаблон формы создания */
    public $actionCreateTemplate = 'create';

    /** @var string Шаблон формы редактирования */
    public $actionUpdateTemplate = 'update';

    /** @var string Шаблон просмотра */
    public $actionViewTemplate = 'view';

    /** @var string Шаблон формы */
    public $formTemplate = '_form';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    // Просмотр списка и карточки
                    [
                        'actions' => ['index', 'view'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\view') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                    // Создание
                    [
                        'actions' => ['create'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\create') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                    // Обновление
                    [
                        'actions' => ['update', 'update-attribute'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\update') ||
                                Yii::$app->user->can('admin');
                        },
                    ],

                    // Удаление
                    [
                        'actions' => ['delete', 'batch-delete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\delete') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                    // Инструкция
                    [
                        'actions' => ['instruction'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'batch-delete' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        $this->_modelName = Yii::$app->request->get('model_class');
        if (!class_exists($this->_modelName)) {
            throw new \yii\web\BadRequestHttpException("Модель не найдена: " . $this->_modelName);
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        /** @var AdminModel $model */
        $model = new AdminModel(\Yii::createObject(['class' => $this->_modelName]));

        // Используем метод search для получения ActiveDataProvider
        $dataProvider = $model->search(ArrayHelper::getValue(\Yii::$app->request->get(), $model->getModel()->formName().'.search'));

        return $this->render($this->actionIndexTemplate, [
            'model' => $model,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        /** @var AdminModel $model */
        $model = new AdminModel(\Yii::createObject(['class' => $this->_modelName]));

        if (!$model->canCreate()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        if (\Yii::$app->request->isPost) {
            $modelData = $model->getModel();
            if ($modelData->load(\Yii::$app->request->post()) && $modelData->save()) {
                if($model->afterSave()) {
                    Yii::$app->session->setFlash('success', 'Запись успешно добавлена');
                    return $this->redirect(['index']);
                }
            }

            Yii::$app->session->setFlash('error', 'Ошибка при добавлении записи: ' . implode(', ', $modelData->getFirstErrors()));
        }

        return $this->render($this->actionCreateTemplate, [
            'model' => $model,
            'formTemplate' => $this->formTemplate,
        ]);
    }

    public function actionUpdate($id)
    {
        /** @var AdminModel $model */
        $model = $this->findModel();

        if (\Yii::$app->request->isPost) {
            $modelData = $model->getModel();
            if ($modelData->load(\Yii::$app->request->post()) && $modelData->save()) {
                if($model->afterSave()) {
                    Yii::$app->session->setFlash('success', 'Запись успешно обновлена');
                    return $this->redirect(['index']);
                }
            }

            Yii::$app->session->setFlash('error', 'Ошибка при обновлении записи: ' . implode(', ', $modelData->getFirstErrors()));
        }

        return $this->render($this->actionUpdateTemplate, [
            'model' => $model,
            'formTemplate' => $this->formTemplate,
        ]);
    }

    public function actionView($id)
    {
        $model = $this->findModel();

        return $this->render($this->actionViewTemplate, [
                'model' => $model,
            ]
        );
    }

    public function actionDelete()
    {
        $adminModel = $this->findModel();

        if($adminModel?->beforeDelete() && $adminModel?->getModel()?->delete()) {
            Yii::$app->session->setFlash('success', 'Запись успешно удалена');
        } else {
            Yii::$app->session->setFlash('error', 'Ошибка удаления');
        }

        return $this->redirect(Url::to(['index']));
    }

    public function actionBatchDelete()
    {
        $ids = Yii::$app->request->post('ids');
        $modelName = $this->_modelName;

        if (empty($ids) || !is_array($ids)) {
            Yii::$app->session->setFlash('error', 'Элементы не выбраны');
            return $this->redirect(['index', 'model_class' => $modelName]);
        }

        $count = 0;
        foreach ($ids as $id) {
            $record = $modelName::findOne($id);
            if ($record) {
                $adminModel = new AdminModel($record);
                if ($adminModel->beforeDelete() && $adminModel->getModel()->delete()) {
                    $count++;
                }
            }
        }

        Yii::$app->session->setFlash('success', "Удалено записей: $count");
        return $this->redirect(Url::to(['index', 'model_class' => $modelName]));
    }


    protected function findModel()
    {
        $modelName = $this->_modelName;
        $keys = [];

        foreach ($modelName::primaryKey() as $key) {
            if ($id = Yii::$app->request->get($key)) {
                $keys[$key] = $id;
            }
        }

        if (empty($keys)) {
            throw new \yii\web\NotFoundHttpException("Не удалось найти модель с таким идентификатором.");
        }

        $model = $modelName::find()->andWhere($keys)->one();
        if ($model !== null) {
            return new AdminModel($model);
        }

        Yii::$app->session->setFlash('error', 'Такая страница не найдена');

        return $this->redirect(['index']);
    }

    /**
     * Пакетное обновление атрибутов (AJAX)
     * @return array
     */
    public function actionUpdateAttribute()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $items = Yii::$app->request->post('items');

        if (empty($items) || !is_array($items)) {
             return ['success' => false, 'message' => 'Нет данных для обновления'];
        }

        $successCount = 0;
        $errors = [];

        foreach ($items as $item) {
            $id = ArrayHelper::getValue($item, 'id');
            $modelClass = str_replace('\\\\', '\\', ArrayHelper::getValue($item, 'model'));
            $attribute = ArrayHelper::getValue($item, 'attribute');
            $value = ArrayHelper::getValue($item, 'value');

            if (!$id || !$modelClass || !$attribute) {
                continue;
            }

            // Optional: Check permissions per model if needed
            // if (!Yii::$app->user->can(...)) ...

            /** @var \yii\db\ActiveRecord $record */
            $record = $modelClass::findOne($id);
            if ($record) {
                $record->{$attribute} = $value;
                 if ($record->save(false)) {
                     $successCount++;
                 } else {
                     $errors[] = "Ошибка сохранения ID {$id}: " . print_r($record->errors, true);
                 }
            }
        }


        return [
            'success' => true, 
            'count' => $successCount, 
            'errors' => $errors
        ];
    }

    /**
     * Показывает инструкцию или форму редактирования
     */
    public function actionInstruction()
    {
        $modelClass = $this->_modelName;
        $info = \Mitisk\Yii2Admin\models\AdminModelInfo::findOne(['model_class' => $modelClass]);

        // Если это запрос на редактирование (или сохранение)
        if (Yii::$app->request->get('edit') || Yii::$app->request->isPost) {
            // Проверка прав на редактирование
            if (!Yii::$app->user->can('superAdminRole')) {
                throw new \yii\web\ForbiddenHttpException('У вас нет прав на редактирование инструкции.');
            }

            if (!$info) {
                $info = new \Mitisk\Yii2Admin\models\AdminModelInfo();
                $info->model_class = $modelClass;
            }

            if ($info->load(Yii::$app->request->post()) && $info->save()) {
                Yii::$app->session->setFlash('success', 'Инструкция сохранена');
                return $this->redirect(['instruction']);
            }

            return $this->render('instruction_edit', [
                'model' => $info,
            ]);
        }

        // Просмотр
        if (!$info) {
            // Если инструкции нет
            if (Yii::$app->user->can('superAdminRole')) {
                // Супер-админа редиректим на создание
                return $this->redirect(['instruction', 'edit' => 1]);
            } else {
                // Обычному пользователю показываем 404 или ничего (хотя по ТЗ кнопка должна быть скрыта)
                throw new \yii\web\NotFoundHttpException("Инструкция не найдена");
            }
        }

        // Если AJAX запрос (как в модалке)
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('instruction', [
                'model' => $info,
                'canEdit' => Yii::$app->user->can('superAdminRole'),
            ]);
        }

        // Если обычный запрос (прямая ссылка)
        return $this->render('instruction', [
            'model' => $info,
            'canEdit' => Yii::$app->user->can('superAdminRole'),
        ]);
    }
}
