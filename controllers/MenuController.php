<?php
namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\Menu;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Редактор меню: боковое меню админки (alias admin) и свои меню проекта.
 * Страница отдаёт все меню одним JSON, сохранение — одним запросом (экран — assets/src/admin/menu-editor.js).
 */
class MenuController extends BaseController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['superAdminRole']
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'save' => ['POST'],
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $menus = [];
        foreach (Menu::find()->orderBy(['ordering' => SORT_ASC, 'id' => SORT_ASC])->all() as $menu) {
            $items = json_decode((string) $menu->data, true);
            $menus[] = [
                'id' => (int) $menu->id,
                'alias' => (string) $menu->alias,
                'name' => (string) $menu->name,
                'locked' => (bool) $menu->not_editable,
                'items' => Menu::sanitizeItems(is_array($items) ? $items : []),
            ];
        }

        return $this->render('index', [
            'menus' => $menus,
            'rules' => $this->ruleOptions(),
            'links' => $this->linkOptions(),
        ]);
    }

    /**
     * Сохраняет все меню одним запросом: либо все, либо ни одного.
     *
     * POST menus — JSON-список {id, name, alias, items}; id = null — новое меню.
     */
    public function actionSave(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $payload = json_decode((string) Yii::$app->request->post('menus'), true);
        if (!is_array($payload) || $payload === []) {
            return ['success' => false, 'message' => 'Нет данных для сохранения.', 'errors' => []];
        }

        $errors = [];
        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach (array_values($payload) as $index => $row) {
                $error = $this->saveMenu(is_array($row) ? $row : [], $index + 1);
                if ($error !== null) {
                    $errors[$index] = $error;
                }
            }
            if ($errors) {
                $transaction->rollBack();
                return ['success' => false, 'message' => 'Меню не сохранено: ' . reset($errors), 'errors' => $errors];
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('Сохранение меню: ' . $e->getMessage(), __METHOD__);
            return ['success' => false, 'message' => 'Меню не сохранено из-за ошибки на сервере.', 'errors' => []];
        }

        Yii::$app->session->setFlash('success', 'Меню сохранено');

        return ['success' => true, 'message' => 'Меню сохранено', 'errors' => []];
    }

    public function actionDelete($id)
    {
        if (Menu::deleteAll(['id' => (int) $id, 'not_editable' => 0])) {
            Yii::$app->session->setFlash('success', 'Меню удалено');
        } else {
            Yii::$app->session->setFlash('error', 'Это меню удалить нельзя');
        }

        return $this->redirect(['index']);
    }

    /**
     * @param array<string, mixed> $row
     * @return string|null текст ошибки или null
     */
    private function saveMenu(array $row, int $ordering): ?string
    {
        $id = $row['id'] ?? null;
        if ($id !== null && $id !== '') {
            $menu = Menu::findOne((int) $id);
            if ($menu === null) {
                return 'Меню уже удалено — обновите страницу.';
            }
        } else {
            $menu = new Menu();
            $menu->alias = is_string($row['alias'] ?? null) ? trim($row['alias']) : '';
            $menu->not_editable = 0;
        }

        // У системного меню название и алиас не меняются; алиас сохранённого меню не меняется никогда
        if (!$menu->not_editable) {
            $menu->name = is_string($row['name'] ?? null) ? trim($row['name']) : '';
            if ($menu->name === '') {
                return 'Укажите название меню.';
            }
        }

        $items = Menu::sanitizeItems(is_array($row['items'] ?? null) ? $row['items'] : []);
        $menu->data = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $menu->ordering = $ordering;

        if (!$menu->save()) {
            return implode(' ', $menu->getFirstErrors());
        }

        return null;
    }

    /**
     * «Кто видит пункт»: роли и права. Права «создание / изменение / удаление» записей для меню не нужны.
     *
     * @return array{roles: array<int, array{value: string, label: string}>, permissions: array<int, array{value: string, label: string}>}
     */
    private function ruleOptions(): array
    {
        $auth = Yii::$app->authManager;
        $roles = [];
        foreach ($auth->getRoles() as $role) {
            $roles[] = ['value' => $role->name, 'label' => (string) ($role->description ?: $role->name)];
        }
        $permissions = [];
        foreach ($auth->getPermissions() as $permission) {
            if (preg_match('/\\\\(create|update|delete)$/', $permission->name)) {
                continue;
            }
            $permissions[] = ['value' => $permission->name, 'label' => (string) ($permission->description ?: $permission->name)];
        }

        return ['roles' => $roles, 'permissions' => $permissions];
    }

    /**
     * Подсказки для поля «Ссылка»: разделы админки.
     *
     * @return array<int, array{href: string, label: string}>
     */
    private function linkOptions(): array
    {
        $links = [
            ['href' => '/admin/', 'label' => 'Главная'],
            ['href' => '/admin/user/', 'label' => 'Пользователи'],
            ['href' => '/admin/role/', 'label' => 'Роли'],
            ['href' => '/admin/settings/', 'label' => 'Настройки'],
            ['href' => '/admin/log/', 'label' => 'Журнал действий'],
        ];
        $components = AdminModel::find()
            ->select(['name', 'alias'])
            ->where(['view' => 1])
            ->andWhere(['not', ['alias' => null]])
            ->andWhere(['<>', 'alias', ''])
            ->orderBy(['name' => SORT_ASC])
            ->asArray()
            ->all();
        foreach ($components as $component) {
            $links[] = ['href' => '/admin/' . $component['alias'] . '/', 'label' => (string) $component['name']];
        }

        return $links;
    }
}
