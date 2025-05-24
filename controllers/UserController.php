<?php
namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\models\AdminUser;
use yii\helpers\Html;
use yii\web\Controller;
use Yii;

class UserController extends Controller
{
    public function actionIndex()
    {
        $model = new AdminUser();
        $provider = $model->search(Yii::$app->request->queryParams);

        return $this->render('index', compact('provider','model'));
    }

    public function actionCreate()
    {
        $model = new AdminUser();

        if ($model->load(Yii::$app->request->post())) {
            $model->image = '/web/users/noPhoto.png';
            if ($model->saveUser()) {
                if (!$model->name) {
                    $model->name = $model->username;
                }
                Yii::$app->session->setFlash('success', 'Добавлен пользователь: "' . Html::encode($model->name) . '"');
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = AdminUser::findOne($id);

        if ($model->load(Yii::$app->request->post()) && $model->saveUser()) {
            if (!$model->name) {
                $model->name = $model->username;
            }
            Yii::$app->session->setFlash('success', 'Обновлен пользователь: "' . Html::encode($model->name) . '"');
            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete($id)
    {
        $model = AdminUser::findOne($id);
        if (!$model->name) {
            $model->name = $model->username;
        }
        if ($model->delete()) {
            Yii::$app->session->setFlash('success', 'Удален пользователь: "' . Html::encode($model->name) . '"');
        } else {
            Yii::$app->session->setFlash('error', 'Не удалось удалить пользователя: "' . Html::encode($model->name) . '"');
        }
        return $this->redirect(['index']);
    }
}
