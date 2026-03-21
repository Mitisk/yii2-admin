<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\Module;
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
     * Clears the application cache
     * @return Response
     */
    public function actionClearCache()
    {
        if (Yii::$app->request->isPost) {
            Yii::$app->cache->flush();
            return $this->asJson(['success' => true]);
        }
        return $this->asJson(['success' => false]);
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
     * Checks if user exists and returns auth type
     * @return Response|array
     */
    public function actionCheckUser()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $username = Yii::$app->request->post('username');

        if (!$username) {
            return ['success' => false, 'message' => 'Login is required'];
        }

        $user = \Mitisk\Yii2Admin\models\AdminUser::findByUsername($username);

        if (!$user) {
            return [
                'success' => true,
                'type' => 'password',
                'username' => $username
            ];
        }

        if ($user->status == \Mitisk\Yii2Admin\models\AdminUser::STATUS_BLOCKED) {
            return ['success' => false, 'message' => 'Ваш аккаунт заблокирован'];
        }

        $types = [
            \Mitisk\Yii2Admin\models\LoginForm::PASSWORD => 'password',
            \Mitisk\Yii2Admin\models\LoginForm::MFA_PASSWORD => 'mixed',
            \Mitisk\Yii2Admin\models\LoginForm::MFA => 'otp',
        ];

        return [
            'success' => true,
            'type' => $types[$user->auth_type] ?? 'password',
            'username' => $user->username
        ];
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            Yii::$app->getResponse()->redirect(['/admin/default/index'])->send();
            Yii::$app->end();
        }

        $this->layout = false;

        /** @var $loginForm LoginForm */
        $loginForm = new LoginForm();

        if ($loginForm->load(Yii::$app->request->post())) {
            $loginForm->getAuthTypeByUsername();

            if ($loginForm->password || $loginForm->mfaCode) {
                if ($loginForm->authType == LoginForm::PASSWORD ||
                    $loginForm->authType == LoginForm::MFA) {
                    if ($loginForm->login()) {
                        Yii::$app->getResponse()->redirect(['/admin/default/index'])->send();
                        Yii::$app->end();
                    }
                    $loginForm->password = '';
                }
                if ($loginForm->authType == LoginForm::MFA_PASSWORD) {
                    if ($loginForm->password && $loginForm->mfaCode) {
                        if ($loginForm->login()) {
                            Yii::$app->getResponse()->redirect(['/admin/default/index'])->send();
                            Yii::$app->end();
                        }
                    }
                    if ($loginForm->password) {
                        $loginForm->validatePassword('password', []);
                    }
                }
            }
        }

        return $this->render('login', [
            'model' => $loginForm,
        ]);
    }

    /**
     * Страница обновления — проверка и запуск миграций.
     *
     * @return string
     */
    public function actionUpgrade(): string
    {
        $this->layout = false;

        $migrationPath = Yii::getAlias(
            '@Mitisk/Yii2Admin/migrations'
        );
        $pending = $this->getPendingMigrations($migrationPath);
        $savedVersion = Yii::$app->settings->get(
            'GENERAL', 'version'
        ) ?: '0.0.0';

        return $this->render('upgrade', [
            'currentVersion' => Module::VERSION,
            'savedVersion' => $savedVersion,
            'pendingCount' => count($pending),
            'pendingList' => $pending,
        ]);
    }

    /**
     * AJAX: запуск миграций.
     *
     * @return array
     */
    public function actionRunMigrations(): Response
    {
        $migrationPath = Yii::getAlias(
            '@Mitisk/Yii2Admin/migrations'
        );
        $pending = $this->getPendingMigrations($migrationPath);

        if (empty($pending)) {
            Yii::$app->settings->set(
                'GENERAL', 'version',
                Module::VERSION, 'string'
            );
            return $this->asJson([
                'success' => true,
                'message' => 'Нет миграций для применения.',
                'applied' => [],
            ]);
        }

        $applied = [];
        $errors = [];

        foreach ($pending as $migrationName) {
            $file = $migrationPath . '/'
                . $migrationName . '.php';
            if (!is_file($file)) {
                $errors[] = $migrationName
                    . ': файл не найден';
                continue;
            }

            ob_start();
            try {
                include_once $file;
                $migration = new $migrationName();
                if ($migration->up() === false) {
                    $output = ob_get_clean();
                    $errors[] = $migrationName
                        . ': ' . ($output ?: 'failed');
                    break;
                }
                ob_end_clean();

                Yii::$app->db->createCommand()->insert(
                    '{{%migration}}',
                    [
                        'version' => $migrationName,
                        'apply_time' => time(),
                    ]
                )->execute();

                $applied[] = $migrationName;
            } catch (\Throwable $e) {
                ob_end_clean();
                $errors[] = $migrationName
                    . ': ' . $e->getMessage();
                break;
            }
        }

        if (empty($errors)) {
            Yii::$app->settings->set(
                'GENERAL', 'version',
                Module::VERSION, 'string'
            );
        }

        return $this->asJson([
            'success' => empty($errors),
            'applied' => $applied,
            'errors' => $errors,
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

    /**
     * Возвращает список непримененных миграций модуля.
     *
     * @param string $migrationPath Путь к директории миграций
     * @return string[] Имена классов миграций
     */
    private function getPendingMigrations(
        string $migrationPath
    ): array {
        if (!is_dir($migrationPath)) {
            return [];
        }

        $files = glob($migrationPath . '/m*.php');
        if (!$files) {
            return [];
        }

        // Получаем уже применённые миграции
        try {
            $appliedRaw = Yii::$app->db
                ->createCommand(
                    'SELECT version FROM {{%migration}}'
                )
                ->queryColumn();
        } catch (\Throwable $e) {
            $appliedRaw = [];
        }
        $applied = array_flip($appliedRaw);

        $pending = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!isset($applied[$name])) {
                $pending[] = $name;
            }
        }

        sort($pending);
        return $pending;
    }
}
