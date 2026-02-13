<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\models\AdminModelInfo;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ModelInfoController extends BaseController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['view'],
                        'allow' => true,
                        'roles' => ['@'], 
                    ],
                    [
                        'actions' => ['update'],
                        'allow' => true,
                        'roles' => ['superAdminRole'],
                    ],
                ],
            ],
        ];
    }

    public function actionView($model)
    {
        $info = AdminModelInfo::findOne(['model_class' => $model]);
        
        if (!$info) {
             throw new NotFoundHttpException("Info not found");
        }
        
        return $this->renderAjax('view', [
            'model' => $info,
            'canEdit' => Yii::$app->user->can('superAdminRole'),
        ]);
    }

    public function actionUpdate($model)
    {
        $info = AdminModelInfo::findOne(['model_class' => $model]);
        if (!$info) {
            $info = new AdminModelInfo();
            $info->model_class = $model;
        }

        if ($info->load(Yii::$app->request->post()) && $info->save()) {
            return $this->redirect(Yii::$app->request->referrer ?: ['/admin']);
        }

        return $this->render('update', [
            'model' => $info,
        ]);
    }
}
