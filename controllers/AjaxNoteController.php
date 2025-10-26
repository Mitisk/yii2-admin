<?php

namespace Mitisk\Yii2Admin\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use Mitisk\Yii2Admin\models\AdminNote;

class AjaxNoteController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'save' => ['POST'],
                    'get'  => ['GET'],
                ],
            ],
        ];
    }

    public function actionGet()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $userId = Yii::$app->user->id;
        $note = AdminNote::find()->where(['user_id' => $userId])->one();
        return [
            'ok'   => true,
            'text' => $note ? (string)$note->text : '',
        ];
    }

    public function actionSave()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $userId = Yii::$app->user->id;
        $text   = Yii::$app->request->post('text', '');

        $note = AdminNote::find()->where(['user_id' => $userId])->one();
        if (!$note) {
            $note = new AdminNote();
            $note->user_id = $userId;
        }
        $note->text = $text;

        if ($note->save()) {
            return ['ok' => true, 'updated_at' => $note->updated_at];
        }

        return ['ok' => false, 'errors' => $note->getErrors()];
    }
}