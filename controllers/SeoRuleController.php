<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\models\SeoRule;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Контроллер управления SEO-правилами.
 */
class SeoRuleController extends BaseController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['superAdminRole', 'admin'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'toggle' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Список SEO-правил.
     */
    public function actionIndex(): string
    {
        $searchModel = new SeoRule();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Создание нового правила.
     */
    public function actionCreate(): string|Response
    {
        $model = new SeoRule();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'SEO-правило создано');
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Редактирование правила.
     */
    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'SEO-правило обновлено');
            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Удаление правила.
     */
    public function actionDelete(int $id): Response
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'SEO-правило удалено');
        return $this->redirect(['index']);
    }

    /**
     * AJAX: переключение активности правила.
     */
    public function actionToggle(): Response
    {
        $id = (int) Yii::$app->request->post('id');
        $model = $this->findModel($id);
        $model->is_active = !$model->is_active;
        $model->save(false, ['is_active']);

        return $this->asJson([
            'success' => true,
            'is_active' => (bool) $model->is_active,
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function findModel(int $id): SeoRule
    {
        if (($model = SeoRule::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('SEO-правило не найдено.');
    }
}
