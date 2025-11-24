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
                        'actions' => ['update'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\update') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                    // Удаление
                    [
                        'actions' => ['delete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\delete') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
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
}