<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Yii;
use Mitisk\Yii2Admin\models\LoginForm;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ErrorAction;
use yii\web\Response;

/**
 * Default controller for the `admin` module
 */
class DefaultController extends BaseController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
                'view' => 'error'
            ],
        ];
    }

    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            Yii::$app->getResponse()->redirect('/admin/')->send();
            Yii::$app->end();
        }

        $this->layout = 'login';

        /** @var $loginForm LoginForm */
        $loginForm = new LoginForm();

        if ($loginForm->load(Yii::$app->request->post())) {
            $loginForm->getAuthTypeByUsername();

            if ($loginForm->password || $loginForm->mfaCode) {
                if ($loginForm->authType == LoginForm::PASSWORD ||
                    $loginForm->authType == LoginForm::MFA) {
                    if ($loginForm->login()) {
                        Yii::$app->getResponse()->redirect('/admin/')->send();
                        Yii::$app->end();
                    }
                }
                if ($loginForm->authType == LoginForm::MFA_PASSWORD) {
                    if ($loginForm->password && $loginForm->mfaCode) {
                        if ($loginForm->login()) {
                            Yii::$app->getResponse()->redirect('/admin/')->send();
                            Yii::$app->end();
                        }
                    }
                    if ($loginForm->password) {
                        $loginForm->validatePassword('password', []);
                    }
                }
            }
        }

        $loginForm->password = '';
        return $this->render('login', [
            'model' => $loginForm,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
