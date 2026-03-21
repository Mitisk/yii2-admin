<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use Mitisk\Yii2Admin\models\AdminNote;

/**
 * Handles AJAX save/get for admin user notes.
 *
 * @category Controller
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 */
class AjaxNoteController extends Controller
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'save' => ['POST'],
                    'get'  => ['GET'],
                ],
            ],
        ];
    }

    /**
     * Returns the current user's note text.
     *
     * @return array
     */
    public function actionGet(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $userId = Yii::$app->user->id;
        $note = AdminNote::find()->where(['user_id' => $userId])->one();
        return [
            'ok'   => true,
            'text' => $note ? (string)$note->text : '',
        ];
    }

    /**
     * Saves note text for the current user.
     *
     * @return array
     */
    public function actionSave(): array
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
