<?php
namespace Mitisk\Yii2Admin\core\controllers;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Yii;
use yii\data\ActiveDataProvider;

class AdminController extends \yii\web\Controller
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

    public function beforeAction($action)
    {
        $this->_modelName = Yii::$app->request->get('model_class');
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $model = \Yii::createObject(['class' => $this->_modelName]);
        $dataProvider = $model/*->search(\Yii::$app->request->queryParams)*/;

        return $this->render($this->actionIndexTemplate, [
            'model' => new AdminModel($model),
            'dataProvider' => new ActiveDataProvider([
                'query' => $model->find(),
            ]),
        ]);
    }

    public function actionCreate()
    {
        /** @var AdminModel $model */
        $model = $this->findModel();

        if(!$model->canCreate()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        if(\Yii::$app->request->isPost)
        {
            if($model->getModel()->load(\Yii::$app->request->post()) && $model->getModel()->save()) {
                Yii::$app->session->setFlash('success', 'Запись успешно добавлена');
                return $this->redirect(['index']);
            }
        }

        return $this->render($this->actionCreateTemplate, [
            'model' => $model,
            'formTemplate' => $this->formTemplate,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel();

        if(\Yii::$app->request->isPost)
        {
            if($model->getModel()->load(\Yii::$app->request->post()) && $model->getModel()->save()) {
                Yii::$app->session->setFlash('success', 'Запись успешно обновлена');
                return $this->redirect(['index']);
            }
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
        if($this->findModel()->getModel()->delete()) {
            Yii::$app->session->setFlash('success', 'Запись успешно удалена');
        } else {
            Yii::$app->session->setFlash('error', 'Ошибка удаления');
        }

        return $this->redirect(['index']);
    }


    protected function findModel()
    {
        $modelName = $this->_modelName;

        $keys = [];
        foreach($modelName::primaryKey() as $key)
        {
            if(\Yii::$app->request->get($key))
                $keys[$key] = \Yii::$app->request->get($key);
        }
        if(!($keys))
            $keys['id'] = -1;

        if($model = $modelName::find()->andWhere($keys)->one()) {
            return new AdminModel($model);
        }

        Yii::$app->session->setFlash('error', 'Такая страница не найдена');

        return $this->redirect(['index']);
    }
}