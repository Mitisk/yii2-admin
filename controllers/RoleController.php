<?php
namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\AdminUser;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ArrayDataProvider;
use Yii;

/**
 * Контроллер для управления RBAC ролями и разрешениями
 */
class RoleController extends Controller
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
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return Yii::$app->user->can('manageRoles');
                        }
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'delete-permission' => ['POST'],
                    'assign-permission' => ['POST'],
                    'revoke-permission' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Список ролей
     */
    public function actionIndex()
    {
        $auth = Yii::$app->authManager;
        $roles = $auth->getRoles();

        $search = trim((string)Yii::$app->request->get('search', ''));

        if ($search !== '') {
            $roles = array_filter($roles, static function($role) use ($search) {
                $name = (string)$role->name;
                $desc = (string)$role->description;
                // регистронезависимый поиск по name|description
                return (mb_stripos($name, $search) !== false) || (mb_stripos((string)$desc, $search) !== false);
            }); // фильтрация перед передачей в ArrayDataProvider
        }

        // Подготовка данных для грида
        $rolesData = [];
        foreach ($roles as $role) {
            $permissions = $auth->getPermissionsByRole($role->name);
            $children = $auth->getChildren($role->name);

            $rolesData[] = [
                'name' => $role->name,
                'description' => $role->description,
                'type' => $role->type,
                'createdAt' => $role->createdAt,
                'updatedAt' => $role->updatedAt,
                'permissionsCount' => count($permissions),
                'childrenCount' => count($children),
                'usersCount' => $this->getUsersCountByRole($role->name),
            ];
        }

        $dataProvider = new ArrayDataProvider([
            'allModels' => $rolesData,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'attributes' => ['name', 'description', 'permissionsCount', 'usersCount'],
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Просмотр роли
     */
    public function actionView($name)
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($name);

        if (!$role) {
            throw new NotFoundHttpException('Роль не найдена.');
        }

        // Получаем разрешения роли
        $permissions = $auth->getPermissionsByRole($name);
        $children = $auth->getChildren($name);
        $users = $this->getUsersByRole($name);

        return $this->render('view', [
            'role' => $role,
            'permissions' => $permissions,
            'children' => $children,
            'users' => $users,
        ]);
    }

    /**
     * Создание роли
     */
    public function actionCreate()
    {
        $auth = Yii::$app->authManager;
        $models = AdminModel::find()->where(['view' => 1])->andWhere(['not', ['alias' => null]])->all();

        if (Yii::$app->request->isPost) {
            $data = Yii::$app->request->post();

            $roleName = $data['name'] ?? '';
            $roleDescription = $data['description'] ?? '';
            $selectedPermissions = $data['permissions'] ?? [];
            $parentRoles = $data['parent_roles'] ?? [];

            // Валидация
            if (empty($roleName)) {
                Yii::$app->session->setFlash('error', 'Имя роли обязательно для заполнения.');
                return $this->render('create', [
                    'availablePermissions' => $this->getAvailablePermissions(),
                    'availableRoles' => $this->getAvailableRoles(),
                    'models' => $models,
                ]);
            }

            if ($auth->getRole($roleName)) {
                Yii::$app->session->setFlash('error', 'Роль с таким именем уже существует.');
                return $this->render('create', [
                    'availablePermissions' => $this->getAvailablePermissions(),
                    'availableRoles' => $this->getAvailableRoles(),
                    'models' => $models,
                ]);
            }

            try {
                // Создаем роль
                $role = $auth->createRole($roleName);
                $role->description = $roleDescription;
                $auth->add($role);

                // Проверяем разрешения и создаем их если нужно
                $this->ensureModelActionPermissions($selectedPermissions, $models);

                // Назначаем разрешения
                $this->assignPermissionsToRole($roleName, $selectedPermissions);

                // Назначаем родительские роли
                $this->assignParentRoles($roleName, $parentRoles);

                Yii::$app->session->setFlash('success', "Роль '{$roleName}' успешно создана.");
                return $this->redirect(['view', 'name' => $roleName]);

            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 'Ошибка создания роли: ' . $e->getMessage());
            }
        }

        return $this->render('create', [
            'availablePermissions' => $this->getAvailablePermissions(),
            'availableRoles' => $this->getAvailableRoles(),
            'models' => $models,
        ]);
    }

    /**
     * Гарантирует существование permissions из $selectedPermissions.
     * Поддерживает формат: FQCN модели + "\" + действие (view|create|update|delete),
     * например: "app\models\ApiKeys\view".
     *
     * @param string[] $selectedPermissions
     * @param AdminModel[] $models
     * @return void
     */
    protected function ensureModelActionPermissions(array $selectedPermissions, array $models): void
    {
        $auth = Yii::$app->authManager;

        // Карта "класс модели" => "человекочитаемое имя" (alias), если доступно
        $aliasByClass = [];
        foreach ($models as $m) {
            // При необходимости подстройте получение FQCN из модели AdminModel
            // Например: $m->class или $m->model_class и т.п.
            $fqcn = $m->class ?? $m->model ?? $m->fqcn ?? null;
            if ($fqcn) {
                $aliasByClass[$fqcn] = $m->alias ?? null;
            }
        }

        // Разрешенные действия
        $allowedActions = ['view' => 'Просмотр', 'create' => 'Создание', 'update' => 'Изменение', 'delete' => 'Удаление'];

        // Регулярное выражение для "FQCN\action"
        // Пример matсh: app\models\ApiKeys\view
        $pattern = '#^(?<class>[A-Za-z0-9_\\\\]+)\\\\(?<action>view|create|update|delete)$#';

        foreach (array_unique($selectedPermissions) as $permName) {
            // Уже существует — пропускаем
            if ($auth->getPermission($permName)) {
                continue;
            }

            $description = null;

            if (preg_match($pattern, $permName, $m)) {
                $modelClass = $m['class'];
                $action = $m['action'];

                $modelTitle = $aliasByClass[$modelClass]
                    ?? \yii\helpers\StringHelper::basename($modelClass);

                $allowedActions = ['view' => 'Просмотр', 'create' => 'Создание', 'update' => 'Изменение', 'delete' => 'Удаление'];
                $actionTitle = $allowedActions[$action] ?? ucfirst($action);
                $description = "{$actionTitle}: {$modelTitle}";
            } else {
                // Неформатные строки тоже можем создать, но помечаем описанием по умолчанию
                $description = 'Пользовательское разрешение';
            }

            // Создаем Permission и добавляем в RBAC хранилище
            $perm = $auth->createPermission($permName);
            $perm->description = $description;
            $auth->add($perm);
        }
    }

    /**
     * Редактирование роли
     */
    public function actionUpdate($name)
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($name);
        $models = AdminModel::find()->where(['view' => 1])->andWhere(['not', ['alias' => null]])->all();
        $usersCount = $this->getUsersCountByRole($name);

        if (!$role) {
            throw new NotFoundHttpException('Роль не найдена.');
        }

        // Проверяем, можно ли редактировать роль
        if ($this->isProtectedRole($name)) {
            //Yii::$app->session->setFlash('warning', 'Данная роль защищена от изменений.');
        }

        $currentPermissions = array_keys($auth->getPermissionsByRole($name));
        $children = $auth->getChildren($name);
        $currentParents = array_keys($children);

        if (Yii::$app->request->isPost && !$this->isProtectedRole($name)) {
            $data = Yii::$app->request->post();

            $roleDescription = $data['description'] ?? '';
            $selectedPermissions = $data['permissions'] ?? [];
            $parentRoles = $data['parent_roles'] ?? [];

            try {
                // Обновляем описание роли
                $role->description = $roleDescription;
                $auth->update($name, $role);

                // Проверяем разрешения и создаем их если нужно
                $this->ensureModelActionPermissions($selectedPermissions, $models);

                // Обновляем разрешения
                $this->updateRolePermissions($name, $selectedPermissions);

                // Обновляем родительские роли
                $this->updateRoleParents($name, $parentRoles);

                Yii::$app->session->setFlash('success', "Роль '{$name}' успешно обновлена.");
                return $this->redirect(['view', 'name' => $name]);

            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 'Ошибка обновления роли: ' . $e->getMessage());
            }
        }

        return $this->render('update', [
            'role' => $role,
            'availablePermissions' => $this->getAvailablePermissions(),
            'availableRoles' => $this->getAvailableRoles($name),
            'currentPermissions' => $currentPermissions,
            'currentParents' => $currentParents,
            'isProtected' => $this->isProtectedRole($name),
            'models' => $models,
            'usersCount' => $usersCount,
            'children' => $children,
        ]);
    }

    /**
     * Удаление роли
     */
    public function actionDelete($name)
    {
        $auth = Yii::$app->authManager;

        if ($this->isProtectedRole($name)) {
            Yii::$app->session->setFlash('error', 'Данная роль защищена от удаления.');
            return $this->redirect(['index']);
        }

        $role = $auth->getRole($name);

        if (!$role) {
            throw new NotFoundHttpException('Роль не найдена.');
        }

        // Проверяем, есть ли пользователи с этой ролью
        $usersCount = $this->getUsersCountByRole($name);
        if ($usersCount > 0) {
            Yii::$app->session->setFlash('error',
                "Невозможно удалить роль '{$name}'. Она назначена {$usersCount} пользователям.");
            return $this->redirect(['index']);
        }

        try {
            $auth->remove($role);
            Yii::$app->session->setFlash('success', "Роль '{$name}' успешно удалена.");
        } catch (\Exception $e) {
            Yii::$app->session->setFlash('error', 'Ошибка удаления роли: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    /**
     * Управление разрешениями
     */
    public function actionPermissions()
    {
        $auth = Yii::$app->authManager;
        $permissions = $auth->getPermissions();

        $search = trim((string)Yii::$app->request->get('search', ''));

        if ($search !== '') {
            $permissions = array_filter($permissions, static function($permission) use ($search) {
                $name = (string)$permission->name;
                $desc = (string)$permission->description;
                // регистронезависимый поиск по name|description
                return (mb_stripos($name, $search) !== false) || (mb_stripos((string)$desc, $search) !== false);
            }); // фильтрация перед передачей в ArrayDataProvider
        }

        // Подготовка данных
        $permissionsData = [];
        foreach ($permissions as $permission) {
            $rolesWithPermission = $this->getRolesWithPermission($permission->name);

            $permissionsData[] = [
                'name' => $permission->name,
                'description' => $permission->description,
                'type' => $permission->type,
                'createdAt' => $permission->createdAt,
                'rolesCount' => count($rolesWithPermission),
                'roles' => $rolesWithPermission,
            ];
        }

        $dataProvider = new ArrayDataProvider([
            'allModels' => $permissionsData,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'attributes' => ['name', 'description', 'rolesCount'],
            ],
        ]);

        return $this->render('permissions', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Создание разрешения
     * @NOTE Не используется
     */
    public function actionCreatePermission()
    {
        $auth = Yii::$app->authManager;

        if (Yii::$app->request->isPost) {
            $data = Yii::$app->request->post();

            $permissionName = $data['name'] ?? '';
            $permissionDescription = $data['description'] ?? '';

            if (empty($permissionName)) {
                Yii::$app->session->setFlash('error', 'Имя разрешения обязательно для заполнения.');
                return $this->render('create-permission');
            }

            if ($auth->getPermission($permissionName)) {
                Yii::$app->session->setFlash('error', 'Разрешение с таким именем уже существует.');
                return $this->render('create-permission');
            }

            try {
                $permission = $auth->createPermission($permissionName);
                $permission->description = $permissionDescription;
                $auth->add($permission);

                Yii::$app->session->setFlash('success', "Разрешение '{$permissionName}' успешно создано.");
                return $this->redirect(['permissions']);

            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 'Ошибка создания разрешения: ' . $e->getMessage());
            }
        }

        return $this->render('create-permission');
    }

    /**
     * Удаление разрешения
     */
    public function actionDeletePermission($name)
    {
        $auth = Yii::$app->authManager;

        if ($this->isProtectedPermission($name)) {
            Yii::$app->session->setFlash('error', 'Данное разрешение защищено от удаления.');
            return $this->redirect(['permissions']);
        }

        $permission = $auth->getPermission($name);

        if (!$permission) {
            throw new NotFoundHttpException('Разрешение не найдено.');
        }

        try {
            $auth->remove($permission);
            Yii::$app->session->setFlash('success', "Разрешение '{$name}' успешно удалено.");
        } catch (\Exception $e) {
            Yii::$app->session->setFlash('error', 'Ошибка удаления разрешения: ' . $e->getMessage());
        }

        return $this->redirect(['permissions']);
    }

    /**
     * AJAX назначение разрешения роли
     */
    public function actionAssignPermission()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $roleName = Yii::$app->request->post('role');
        $permissionName = Yii::$app->request->post('permission');

        if (!$roleName || !$permissionName) {
            return ['success' => false, 'message' => 'Недостаточно данных'];
        }

        try {
            $auth = Yii::$app->authManager;
            $role = $auth->getRole($roleName);
            $permission = $auth->getPermission($permissionName);

            if (!$role || !$permission) {
                return ['success' => false, 'message' => 'Роль или разрешение не найдены'];
            }

            $auth->addChild($role, $permission);

            return [
                'success' => true,
                'message' => "Разрешение '{$permissionName}' назначено роли '{$roleName}'"
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * AJAX отзыв разрешения роли
     */
    public function actionRevokePermission()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $roleName = Yii::$app->request->post('role');
        $permissionName = Yii::$app->request->post('permission');

        if (!$roleName || !$permissionName) {
            return ['success' => false, 'message' => 'Недостаточно данных'];
        }

        try {
            $auth = Yii::$app->authManager;
            $role = $auth->getRole($roleName);
            $permission = $auth->getPermission($permissionName);

            if (!$role || !$permission) {
                return ['success' => false, 'message' => 'Роль или разрешение не найдены'];
            }

            $auth->removeChild($role, $permission);

            return [
                'success' => true,
                'message' => "Разрешение '{$permissionName}' отозвано у роли '{$roleName}'"
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    // === ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ===

    /**
     * Получить доступные разрешения
     */
    protected function getAvailablePermissions()
    {
        $auth = Yii::$app->authManager;
        $permissions = $auth->getPermissions();

        $result = [];
        foreach ($permissions as $permission) {
            $result[$permission->name] = $permission->description ?: $permission->name;
        }

        return $result;
    }

    /**
     * Получить доступные роли
     */
    protected function getAvailableRoles($excludeRole = null)
    {
        $auth = Yii::$app->authManager;
        $roles = $auth->getRoles();

        $result = [];
        foreach ($roles as $role) {
            if ($excludeRole && $role->name === $excludeRole) {
                continue;
            }
            $result[$role->name] = $role->description ?: $role->name;
        }

        return $result;
    }

    /**
     * Назначить разрешения роли
     */
    protected function assignPermissionsToRole($roleName, $permissions)
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);

        foreach ($permissions as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                try {
                    $auth->addChild($role, $permission);
                } catch (\Exception $e) {
                    // Разрешение уже назначено
                }
            }
        }
    }

    /**
     * Назначить родительские роли
     */
    protected function assignParentRoles($roleName, $parentRoles)
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);

        foreach ($parentRoles as $parentRoleName) {
            $parentRole = $auth->getRole($parentRoleName);
            if ($parentRole) {
                try {
                    $auth->addChild($role, $parentRole);
                } catch (\Exception $e) {
                    // Роль уже является дочерней
                }
            }
        }
    }

    /**
     * Обновить разрешения роли
     */
    protected function updateRolePermissions($roleName, $newPermissions)
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);

        // Получаем текущие разрешения
        $currentPermissions = array_keys($auth->getPermissionsByRole($roleName));

        // Удаляем лишние
        $toRemove = array_diff($currentPermissions, $newPermissions);
        foreach ($toRemove as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                $auth->removeChild($role, $permission);
            }
        }

        // Добавляем новые
        $toAdd = array_diff($newPermissions, $currentPermissions);
        foreach ($toAdd as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission) {
                try {
                    $auth->addChild($role, $permission);
                } catch (\Exception $e) {
                    // Уже назначено
                }
            }
        }
    }

    /**
     * Обновить родительские роли
     */
    protected function updateRoleParents($roleName, $newParents)
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);

        // Получаем текущих родителей (только роли, не разрешения)
        $children = $auth->getChildren($roleName);
        $currentParents = [];
        foreach ($children as $child) {
            if ($child->type == 1) { // Роль
                $currentParents[] = $child->name;
            }
        }

        // Удаляем лишние
        $toRemove = array_diff($currentParents, $newParents);
        foreach ($toRemove as $parentRoleName) {
            $parentRole = $auth->getRole($parentRoleName);
            if ($parentRole) {
                $auth->removeChild($role, $parentRole);
            }
        }

        // Добавляем новые
        $toAdd = array_diff($newParents, $currentParents);
        foreach ($toAdd as $parentRoleName) {
            $parentRole = $auth->getRole($parentRoleName);
            if ($parentRole) {
                try {
                    $auth->addChild($role, $parentRole);
                } catch (\Exception $e) {
                    // Уже назначено или создает цикл
                }
            }
        }
    }

    /**
     * Получить количество пользователей с ролью
     */
    protected function getUsersCountByRole(string $roleName): int
    {
        $auth = Yii::$app->authManager;
        // Предпочтительно через RBAC API
        if (method_exists($auth, 'getUserIdsByRole')) {
            return count($auth->getUserIdsByRole($roleName));
        }
        // Fallback для совместимости: прямой запрос к таблице назначений (DbManager)
        if ($auth instanceof \yii\rbac\DbManager) {
            return (new \yii\db\Query())
                ->from($auth->assignmentTable)
                ->where(['item_name' => $roleName])
                ->count('*', $auth->db);
        }
        return 0;
    }

    /**
     * Получить пользователей с ролью
     */
    protected function getUsersByRole($roleName)
    {
        $auth = Yii::$app->authManager;

        // Предпочтительно через RBAC API
        if (method_exists($auth, 'getUserIdsByRole') && $auth->getUserIdsByRole($roleName)) {
            $model = new AdminUser();
            return $model->search(Yii::$app->request->queryParams, ids: $auth->getUserIdsByRole($roleName));
        }

        return [];
    }

    /**
     * Получить роли с определенным разрешением
     */
    protected function getRolesWithPermission($permissionName)
    {
        $auth = Yii::$app->authManager;
        $roles = $auth->getRoles();
        $result = [];

        foreach ($roles as $role) {
            $permissions = $auth->getPermissionsByRole($role->name);
            if (isset($permissions[$permissionName])) {
                $result[] = $role->name;
            }
        }

        return $result;
    }

    /**
     * Проверить, защищена ли роль от изменений
     */
    protected function isProtectedRole($roleName)
    {
        $protectedRoles = ['admin', 'superAdminRole']; // Можно вынести в конфиг
        return in_array($roleName, $protectedRoles);
    }

    /**
     * Проверить, защищено ли разрешение от удаления
     */
    protected function isProtectedPermission($permissionName)
    {
        $protectedPermissions = ['superAdmin', 'manageUserRoles', 'manageRoles'];
        return in_array($permissionName, $protectedPermissions);
    }
}
