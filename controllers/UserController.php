<?php
namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\models\AdminUser;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use Yii;

class UserController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can('viewUsers');
                        }
                    ],
                    [
                        'actions' => ['create'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can('createUsers');
                        }
                    ],
                    [
                        'actions' => ['update'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can('updateUsers');
                        }
                    ],
                    [
                        'actions' => ['delete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can('deleteUsers');
                        }
                    ],
                    [
                        'actions' => ['assign-role', 'revoke-role'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can('manageUserRoles');
                        }
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'assign-role' => ['POST'],
                    'revoke-role' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $model = new AdminUser();
        $provider = $model->search(Yii::$app->request->queryParams);

        return $this->render('index', compact('provider','model'));
    }

    public function actionCreate()
    {
        $model = new AdminUser();
        $assignedRoles = [];

        if ($model->load(Yii::$app->request->post())) {
            $model->image = '/web/users/noPhoto.png';

            // Получаем роли из POST данных
            $postRoles = Yii::$app->request->post('user_roles', []);

            if ($model->saveUser()) {
                // Назначаем выбранные роли
                $this->assignRolesToUser($model->id, $postRoles);

                if (!$model->name) {
                    $model->name = $model->username;
                }
                Yii::$app->session->setFlash('success', 'Добавлен пользователь: "' . Html::encode($model->name) . '"');
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', [
            'model' => $model,
            'availableRoles' => $this->getAvailableRoles(),
            'assignedRoles' => $assignedRoles,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = AdminUser::findOne($id);

        if (!$model) {
            throw new NotFoundHttpException('Пользователь не найден.');
        }

        // Получаем текущие роли пользователя
        $assignedRoles = $this->getUserRoles($id);

        if ($model->load(Yii::$app->request->post())) {
            // Получаем роли из POST данных
            $postRoles = Yii::$app->request->post('user_roles', []);

            if ($model->saveUser()) {
                // Обновляем роли пользователя
                $this->updateUserRoles($id, $postRoles);

                if (!$model->name) {
                    $model->name = $model->username;
                }
                Yii::$app->session->setFlash('success', 'Обновлен пользователь: "' . Html::encode($model->name) . '"');
                return $this->redirect(['index']);
            }
        }

        return $this->render('update', [
            'model' => $model,
            'availableRoles' => $this->getAvailableRoles(),
            'assignedRoles' => $assignedRoles,
        ]);
    }

    public function actionDelete($id)
    {
        $model = AdminUser::findOne($id);

        if (!$model) {
            throw new NotFoundHttpException('Пользователь не найден.');
        }

        if (!$model->name) {
            $model->name = $model->username;
        }

        // Удаляем все назначения ролей перед удалением пользователя
        $this->revokeAllUserRoles($id);

        if ($model->delete()) {
            Yii::$app->session->setFlash('success', 'Удален пользователь: "' . Html::encode($model->name) . '"');
        } else {
            Yii::$app->session->setFlash('error', 'Не удалось удалить пользователя: "' . Html::encode($model->name) . '"');
        }

        return $this->redirect(['index']);
    }

    public function actionView($id)
    {
        $model = AdminUser::findOne($id);

        if (!$model) {
            throw new NotFoundHttpException('Пользователь не найден.');
        }

        $assignedRoles = $this->getUserRoles($id);
        $userPermissions = $this->getUserPermissions($id);

        return $this->render('view', [
            'model' => $model,
            'assignedRoles' => $assignedRoles,
            'userPermissions' => $userPermissions,
        ]);
    }

    /**
     * Получить доступные роли для назначения
     * @return array
     */
    protected function getAvailableRoles()
    {
        $authManager = Yii::$app->authManager;
        $roles = $authManager->getRoles();

        // Фильтруем роли в зависимости от прав текущего пользователя
        $availableRoles = [];
        foreach ($roles as $role) {
            // Проверяем, может ли текущий пользователь назначать эту роль
            if ($this->canAssignRole($role->name)) {
                $availableRoles[$role->name] = $role->description ?: $role->name;
            }
        }

        return $availableRoles;
    }

    /**
     * Получить роли пользователя
     * @param int $userId
     * @return array
     */
    protected function getUserRoles($userId)
    {
        $authManager = Yii::$app->authManager;
        $roles = $authManager->getRolesByUser($userId);
        return ArrayHelper::map($roles, 'name', 'name');
    }

    /**
     * Получить разрешения пользователя
     * @param int $userId
     * @return array
     */
    protected function getUserPermissions($userId)
    {
        $authManager = Yii::$app->authManager;
        $permissions = $authManager->getPermissionsByUser($userId);
        return ArrayHelper::map($permissions, 'name', 'description');
    }

    /**
     * Назначить роли пользователю
     * @param int $userId
     * @param array $roleNames
     */
    protected function assignRolesToUser($userId, $roleNames)
    {
        $authManager = Yii::$app->authManager;

        foreach ($roleNames as $roleName) {
            $role = $authManager->getRole($roleName);
            if ($role && $this->canAssignRole($roleName)) {
                try {
                    $authManager->assign($role, $userId);
                } catch (\Exception $e) {
                    Yii::error("Не удалось назначить роль {$roleName} пользователю {$userId}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Обновить роли пользователя
     * @param int $userId
     * @param array $newRoleNames
     */
    protected function updateUserRoles($userId, $newRoleNames)
    {
        // Получаем текущие роли
        $currentRoles = $this->getUserRoles($userId);

        // Находим роли для удаления
        $rolesToRevoke = array_diff($currentRoles, $newRoleNames);

        // Находим роли для добавления
        $rolesToAssign = array_diff($newRoleNames, $currentRoles);

        $authManager = Yii::$app->authManager;

        // Удаляем роли
        foreach ($rolesToRevoke as $roleName) {
            $role = $authManager->getRole($roleName);
            if ($role && $this->canRevokeRole($roleName)) {
                try {
                    $authManager->revoke($role, $userId);
                } catch (\Exception $e) {
                    Yii::error("Не удалось отозвать роль {$roleName} у пользователя {$userId}: " . $e->getMessage());
                }
            }
        }

        // Назначаем новые роли
        $this->assignRolesToUser($userId, $rolesToAssign);
    }

    /**
     * Отозвать все роли у пользователя
     * @param int $userId
     */
    protected function revokeAllUserRoles($userId)
    {
        $authManager = Yii::$app->authManager;
        $authManager->revokeAll($userId);
    }

    /**
     * Проверить, может ли текущий пользователь назначать роль
     * @param string $roleName
     * @return bool
     */
    protected function canAssignRole($roleName)
    {
        // Здесь можно добавить логику ограничений:
        // - Администратор не может назначить роль выше своей
        // - Некоторые роли могут назначать только super admin

        // Пример: только super admin может назначать admin роль
        if ($roleName === 'admin' && !Yii::$app->user->can('superAdmin')) {
            return false;
        }

        return Yii::$app->user->can('manageUserRoles');
    }

    /**
     * Проверить, может ли текущий пользователь отозвать роль
     * @param string $roleName
     * @return bool
     */
    protected function canRevokeRole($roleName)
    {
        return $this->canAssignRole($roleName);
    }
}