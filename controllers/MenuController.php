<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\models\Menu;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Controller;

/**
 * Class MenuController
 * @package Mitisk\Yii2Admin\controllers
 */
class MenuController extends Controller
{

    public function actionIndex()
    {
        if (Yii::$app->request->isPost) {
            $data = Yii::$app->request->post('Menu');

            $i = 0;

            foreach ($data as $key => $value) {
                if (ArrayHelper::getValue($value, 'data') || ArrayHelper::getValue($value, 'name')) {
                    $i++;
                    /** @var Menu $model */
                    $model = Menu::find()->where(['alias' => $key])->one();
                    if (!$key == 'new' || !$model) {
                        $model = new Menu();
                        $model->alias = ArrayHelper::getValue($value, 'alias');
                    }

                    $model->data = ArrayHelper::getValue($value, 'data');
                    $model->name = ArrayHelper::getValue($value, 'name');
                    $model->ordering = $i;
                    $model->save();
                }
            }
        }

        $models = Menu::find()->orderBy(['ordering' => SORT_ASC])->all();

        return $this->render('index', [
            'models' => $models
        ]);
    }

    public function actionDelete($id)
    {
        if (Menu::deleteAll(['id' => $id, 'not_editable' => 0])) {
            Yii::$app->session->setFlash('success', 'Меню удалено');
        } else {
            Yii::$app->session->setFlash('error', 'Ошибка удаления меню');
        }
        return $this->redirect(['index']);
    }
}
