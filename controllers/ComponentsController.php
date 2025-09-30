<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\models\AdminComponent;
use Mitisk\Yii2Admin\models\AdminModel;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Контроллер управления компонентами в админ-панели.
 */
class ComponentsController extends Controller
{
    /**
     * Подключает фильтры доступа и HTTP-методов.
     * @return array
     */
    public function behaviors() : array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['superAdminRole'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'install' => ['POST'],
                    'uninstall' => ['POST'],
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Список доступных моделей (компонентов) с автодобавлением таблиц из БД.
     * @return string
     */
    public function actionIndex() : string
    {
        $db = Yii::$app->get('db', false);
        $schema = $db ? $db->getSchema() : null;

        if ($schema) {
            $tables = $schema->getTableNames();
            $exists = AdminModel::find()->select('table_name')->column();
            $tables = array_diff($tables, $exists);

            foreach ($tables as $tableName) {
                $model = new AdminModel();
                $model->name = $tableName;
                $model->table_name = $tableName;
                $model->save(false);
            }
        }

        $models = AdminModel::find()->where(['view' => 1])->all();
        $helper = Yii::$app->componentHelper;

        return $this->render('index', compact('models', 'helper'));
    }

    public function actionInstall()
    {
        $alias = Yii::$app->request->post('alias');
        $helper = Yii::$app->componentHelper;

        if (AdminComponent::find()->where(['alias' => $alias])->exists()) {
            if ($data = $helper->updateComponent($alias)) {
                AdminComponent::updateAll([
                    'name' => $data['name'],
                    'version' => $data['version'],
                    'datetime' => date('Y-m-d H:i:s')
                ], [
                    'alias' => $alias
                ]);

                return true;
            }
            return false;
        }

        if ($data = $helper->installComponent($alias)) {
            $model = new AdminComponent();
            $model->name = $data['name'];
            $model->alias = $data['alias'];
            $model->version = $data['version'];
            return $model->save(false);
        }

        return false;
    }

    public function actionUninstall()
    {
        $alias = Yii::$app->request->post('alias');
        $helper = Yii::$app->componentHelper;
        sleep(1);
        if ($return = $helper->uninstallComponent($alias)) {
            AdminComponent::deleteAll(['alias' => $alias]);
        }
        return $return;
    }

    /**
     * Редактирование конфигурации модели (компонента) и сборки формы.
     * @param int $id
     * @return string|Response
     * @throws NotFoundHttpException
     */
    public function actionUpdate(int $id)
    {
        $model = AdminModel::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        // PRG: после успешного сохранения делаем redirect
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Компонент обновлен.');
            return $this->redirect(['update', 'id' => $model->id]);
        }

        $db = Yii::$app->get('db', false);
        $schema = $db ? $db->getSchema() : null;
        $tableSchema = $schema ? $schema->getTableSchema($model->table_name) : null;

        $columnsNames = [];
        $allColumnsNames = [];

        if ($tableSchema) {
            foreach ($tableSchema->columns as $name => $column) {
                $allColumnsNames[] = $name;
                if (!$column->isPrimaryKey) {
                    $columnsNames[] = $name;
                }
            }
        }

        $modelInstance = null;
        $requiredColumns = [];
        $addedAttributes = [];

        if ($model->model_class) {
            if (!class_exists($model->model_class)) {
                Yii::$app->session->setFlash('error', 'Класс модели не найден.');
                $model->model_class = null;
            } else {
                $modelInstance = new $model->model_class();

                // Добавляем публичные свойства (виртуальные атрибуты) модели
                $publicProps = self::getPublicProperties($model->model_class);
                $columnsNames = ArrayHelper::merge($columnsNames, $publicProps);
                $allColumnsNames = ArrayHelper::merge($allColumnsNames, $publicProps);

                // Требуемые атрибуты по правилам
                foreach ((array)$modelInstance->rules() as $rule) {
                    if (isset($rule[1]) && $rule[1] === 'required') {
                        $attrs = is_array($rule[0]) ? $rule[0] : [$rule[0]];
                        $requiredColumns = array_merge($requiredColumns, $attrs);
                    }
                }
            }
        }

        if ($model->data) {
            $data = json_decode($model->data, true);
            if (is_array($data)) {
                $addedAttributes = ArrayHelper::map($data, 'name', 'name');
            }
        }

        $list = $model->list ? json_decode($model->list, true) : ($allColumnsNames ?: []);
        $this->configureList($list, $allColumnsNames, $modelInstance, $model);
        $model->list = $list;

        $publicStaticMethods = json_encode($model->model_class ? self::getPublicMethods($model->model_class) : []);
        $publicSaveMethods = json_encode($model->model_class ? self::getPublicMethods($model->model_class, true) : []);

        $auth = Yii::$app->authManager;
        $roles = $auth->getRoles();

        return $this->render('update', compact(
            'model',
            'columnsNames',
            'modelInstance',
            'requiredColumns',
            'addedAttributes',
            'allColumnsNames',
            'publicStaticMethods',
            'publicSaveMethods',
            'roles'
        ));
    }

    public function actionDelete($id)
    {
        AdminModel::updateAll(['view' => 0], ['id' => $id]);
        return $this->redirect(['index']);
    }

    /**
     * Имена публичных свойств класса.
     * @param string $className
     * @return array
     */
    private static function getPublicProperties(string $className): array
    {
        return array_map(
            static fn(\ReflectionProperty $property) => $property->getName(),
            (new \ReflectionClass($className))->getProperties(\ReflectionProperty::IS_PUBLIC)
        );
    }

    /**
     * Список методов класса для заполнения select-ов статическими массивами или сохранения через ActiveQuery.
     * @param string $className
     * @param bool $forSave Поиск методов для сохранения (ActiveQuery)
     * @return array<string,string>
     */
    private static function getPublicMethods(string $className, bool $forSave = false) : array
    {
        // Получаем все методы текущего класса
        $methods = get_class_methods($className);

        // Используем рефлексию для фильтрации методов
        $reflectionClass = new \ReflectionClass($className);
        $publicMethods = [];

        foreach ($methods as $method) {
            $reflectionMethod = $reflectionClass->getMethod($method);

            // Проверяем, что метод публичный, и что он определен в данном классе
            if ($reflectionMethod->isPublic() &&
                $reflectionMethod->getDeclaringClass()->getName() === $className) {

                //Поиск методов, возвращающих массив значений
                if (!$forSave) {
                    if ($reflectionMethod->isStatic()) {
                        // Вызываем метод статически
                        $returnValue = $reflectionMethod->invoke(null);
                        // Проверяем, возвращает ли метод массив
                        if (is_array($returnValue)) {
                            $publicMethods[$method] = $className . '::' . $method . '()';
                        }
                    } else {
                        //Если есть зависимости через viaTable
                        $returnType = $reflectionMethod->getReturnType();

                        if ($returnType && $returnType->allowsNull() === false && $returnType->getName() === \yii\db\ActiveQuery::class) {
                            // Вызываем метод связи, чтобы получить экземпляр ActiveQuery
                            $query = (new $className)->{$method}();

                            if ($query instanceof \yii\db\ActiveQuery) {
                                // Проверяем, использует ли связь viaTable
                                if ($query->via !== null) {
                                    $publicMethods[$method] = $className . '::' . $method . '()';
                                }
                            }
                        }

                    }
                } else  {
                    //Поиск методов для сохранения значений
                    $returnType = $reflectionMethod->getReturnType();

                    if ($returnType && $returnType->allowsNull() === false && $returnType->getName() === \yii\db\ActiveQuery::class) {
                        $publicMethods[$method] = $className . '::' . $method . '()';
                    }
                }

            }
        }

        //Добавляем пустую строку
        if ($publicMethods) {
            $publicMethods = array_merge([null => '---'], $publicMethods);
        }

        return $publicMethods;
    }

    /**
     * Строит структуру списка колонок для таблицы в админке.
     * @param array $list
     * @param array $allColumns Список имён колонок/атрибутов
     * @param object|null $modelInstance
     * @param AdminModel $model
     * @return void
     */
    private function configureList(array &$list, array $allColumns, ?object $modelInstance, AdminModel $model): void
    {
        $list['admin_number']['name'] = 'No';
        $list['admin_number']['description'] = 'Порядковый номер';

        $list['admin_checkbox']['name'] = 'Чекбокс';
        $list['admin_checkbox']['description'] = 'Выбрать строку';

        if ($modelInstance) {
            foreach ($allColumns as $column) {
                $list[$column] = is_array(ArrayHelper::getValue($list, $column, [])) ? ArrayHelper::getValue($list, $column, []) : [ArrayHelper::getValue($list, $column, [])];
                $list[$column]['name'] = $modelInstance->getAttributeLabel($column);
                $list[$column]['description'] = $column;
            }
        }

        $list['admin_actions']['name'] = 'Действия';
        $list['admin_actions']['description'] = '<i class="icon-eye js-list-actions ' . (ArrayHelper::getValue($list, 'admin_actions.data.view') ? 'active' : '') . '">'
            . Html::hiddenInput(Html::getInputName($model, 'list').'[admin_actions][data][view]', ArrayHelper::getValue($list, 'admin_actions.data.view', '0'))
            . '</i>
            <i class="icon-edit-3 js-list-actions ' . (ArrayHelper::getValue($list, 'admin_actions.data.update') ? 'active' : '') . '">'
            . Html::hiddenInput(Html::getInputName($model, 'list').'[admin_actions][data][update]', ArrayHelper::getValue($list, 'admin_actions.data.update', '0'))
            . '</i>
            <i class="icon-trash-2 js-list-actions ' . (ArrayHelper::getValue($list, 'admin_actions.data.delete') ? 'active' : '') . '">'
            . Html::hiddenInput(Html::getInputName($model, 'list').'[admin_actions][data][delete]', ArrayHelper::getValue($list, 'admin_actions.data.delete', '0'))
            . '</i>';
    }
}
