<?php
namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\AdminUserMap;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use Yii;
use yii\web\NotFoundHttpException;

class UserController extends BaseController
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
                            // 1. Сначала проверяем глобальное разрешение (например, для админа)
                            if (Yii::$app->user->can('updateUsers')) {
                                return true;
                            }

                            // 2. Если глобального права нет, проверяем, "свой" ли это профиль
                            // Получаем id из URL (например, /user/update?id=5)
                            $requestedId = Yii::$app->request->get('id');

                            // Сравниваем ID из URL с ID текущего пользователя
                            return $requestedId == Yii::$app->user->id;
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
                    [
                        'actions' => ['login-as'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can('admin');
                        }
                    ],
                    [
                        'actions' => ['stop-impersonate'],
                        'allow' => true,
                        'roles' => ['@'],
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

        // Load extra models based on AdminUserMap
        $maps = AdminUserMap::find()->all();
        $extraModels = [];
        foreach ($maps as $map) {
            if (class_exists($map->form)) {
                $extra = new $map->form();
                $extraModels[$map->id] = $extra;
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            
            // Handle uploaded avatar
            // 1. Check specific 'files' input (custom hidden input or fileuploader default for some configs)
            $files = Yii::$app->request->post('files');
            // 2. Check 'fileuploader-list-files' which is the default list input from the plugin for input name="files"
            $fileList = Yii::$app->request->post('fileuploader-list-files');

            $avatarFound = false;

            if ($files) {
                 $filesData = json_decode($files, true);
                 if (json_last_error() === JSON_ERROR_NONE) {
                     if (!empty($filesData['files'][0]['name'])) {
                         $model->image = '/web/users/avatar/' . $filesData['files'][0]['name'];
                         $avatarFound = true;
                     } elseif (isset($filesData[0]['name'])) {
                         $model->image = '/web/users/avatar/' . $filesData[0]['name'];
                         $avatarFound = true;
                     }
                 } elseif (is_string($files) && !empty($files)) {
                      $model->image = '/web/users/avatar/' . $files;
                      $avatarFound = true;
                 }
            } 
            
            if (!$avatarFound && $fileList) {
                $filesData = json_decode($fileList, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($filesData[0]['file'])) {
                    $model->image = '/web/users/avatar/' . $filesData[0]['file'];
                    $avatarFound = true;
                }
            }

            if (!$avatarFound) {
                 $model->image = '/web/users/noPhoto.png';
            }

            // Получаем роли из POST данных
            $postRoles = Yii::$app->request->post('user_roles', []);

            if ($model->saveUser()) {
                // Назначаем выбранные роли
                $this->assignRolesToUser($model->id, $postRoles);

                if (!$model->name) {
                    $model->name = $model->username;
                }

                // Save extra models
                foreach ($extraModels as $extra) {
                    if ($extra instanceof \yii\db\ActiveRecord && $extra->hasAttribute('user_id')) {
                        $extra->user_id = $model->id;
                    }
                    if ($extra && $extra->load(Yii::$app->request->post()) && $extra->validate()) {
                        $extra->save();
                    }
                }

                Yii::$app->session->setFlash('success', 'Добавлен пользователь: "' . Html::encode($model->name) . '"');
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', [
            'model' => $model,
            'availableRoles' => $this->getAvailableRoles(),
            'assignedRoles' => $assignedRoles,
            'maps' => $maps,
            'extraModels' => $extraModels
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

        // Load extra models based on AdminUserMap
        $maps = AdminUserMap::find()->all();
        $extraModels = [];
        foreach ($maps as $map) {
            if (class_exists($map->form)) {
                $extra = null;
                try {
                     // Check if method findOne exists which is standard for AR
                     if (method_exists($map->form, 'findOne')) {
                         $extra = $map->form::findOne(['user_id' => $id]);
                     }
                } catch (\Exception $e) {
                    $extra = null;
                }
                
                if (!$extra) {
                    $extra = new $map->form();
                    if ($extra instanceof \yii\db\ActiveRecord && $extra->hasAttribute('user_id')) {
                        $extra->user_id = $id;
                    }
                }
                $extraModels[$map->id] = $extra;
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            // Получаем роли из POST данных
            $postRoles = Yii::$app->request->post('user_roles', []);

            if ($model->saveUser()) {
                // Обновляем роли пользователя
                $this->updateUserRoles($id, $postRoles);

                if (!$model->name) {
                    $model->name = $model->username;
                }
                
                // Save extra models
                foreach ($extraModels as $extra) {
                    if ($extra && $extra->load(Yii::$app->request->post()) && $extra->validate()) {
                        $extra->save();
                    }
                }

                Yii::$app->session->setFlash('success', 'Обновлен пользователь: "' . Html::encode($model->name) . '"');
                return $this->redirect(['index']);
            }
        }

        return $this->render('update', [
            'model' => $model,
            'availableRoles' => $this->getAvailableRoles(),
            'assignedRoles' => $assignedRoles,
            'maps' => $maps,
            'extraModels' => $extraModels
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
     * Действие для входа под другим пользователем
     */
    public function actionLoginAs($id)
    {
        // 1. Находим целевого пользователя
        $targetUser = AdminUser::findOne($id);
        if (!$targetUser) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        // 2. Проверка безопасности (только супер-админ может это делать)
        if (!Yii::$app->user->can('admin')) {
            throw new \yii\web\ForbiddenHttpException('Доступ запрещен');
        }

        // 3. Запоминаем ID текущего админа в сессию
        // Важно: используем сессию приложения
        Yii::$app->session->set('impersonator_id', Yii::$app->user->id);

        // 4. Логиним пользователя
        // duration = 0 означает, что вход только на время сессии (без "Запомнить меня")
        Yii::$app->user->switchIdentity($targetUser, 0);

        // 5. Редирект на главную страницу
        Yii::$app->session->setFlash('warning', 'Вы вошли в режиме просмотра от имени пользователя ' . $targetUser->username);

        return $this->redirect(['/admin/']);
    }

    /**
     * Действие для возврата обратно в админку
     */
    public function actionStopImpersonate()
    {
        // 1. Проверяем, есть ли запись о "настоящем" админе
        $adminId = Yii::$app->session->get('impersonator_id');

        if ($adminId) {
            $adminUser = AdminUser::findOne($adminId);
            if ($adminUser) {
                // 2. Возвращаем админа
                Yii::$app->user->switchIdentity($adminUser, 0);

                // 3. Чистим сессию
                Yii::$app->session->remove('impersonator_id');

                Yii::$app->session->setFlash('success', 'Вы вернулись в аккаунт администратора');
                return $this->redirect(['/admin/user/index']); // Путь к списку юзеров
            }
        }

        return $this->goHome();
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