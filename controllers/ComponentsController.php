<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\models\AdminModel;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\Controller;

/**
 * Default controller for the `admin` module
 */
class ComponentsController extends Controller
{
    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionIndex()
    {
        $db = \Yii::$app->get('db', false);

        /** @var $schema yii\db\mysql\Schema */
        $schema = $db->getSchema();

        if($schema) {
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

        return $this->render('index', compact(['models']));
    }

    public function actionUpdate($id)
    {
        $model = AdminModel::findOne($id);

        if(!$model) {
            throw new \yii\web\NotFoundHttpException('The requested page does not exist.');
        }

        if(Yii::$app->request->isPost) {
            if($model->load(\Yii::$app->request->post()) && $model->save()) {
                Yii::$app->session->setFlash('success', 'Компонент обновлен.');
            }
        }

        $db = \Yii::$app->get('db', false);

        /** @var $schema yii\db\mysql\Schema */
        $schema = $db->getSchema();
        $columns = $allColumns = $schema->getTableSchema($model->table_name)->columns;

        if($columns) {
            foreach ($columns as $key => $column) {
                if($column->isPrimaryKey) {
                    unset($columns[$key]);
                }
            }
        }

        $columns = ArrayHelper::merge(array_keys($columns), self::getPublicProperties($model->model_class));
        $allColumns = array_keys($allColumns);

        $modelInstance = null;
        $requiredColumns = [];
        $addedAttributes = [];


        if($model->model_class) {
            if (!class_exists($model->model_class)) {
                $model->model_class = null;
            } else {
                $modelInstance = new $model->model_class();
                $rules = $modelInstance->rules();

                //get all required columns
                if ($rules) {
                    foreach ($rules as $rule) {
                        if (isset($rule[1]) && $rule[1] == 'required') {
                            $requiredColumns = array_merge($requiredColumns, is_array($rule[0]) ? $rule[0] : [$rule[0]]);
                        }
                    }
                }
            }
        }

        if($model->data) {
            $data = json_decode($model->data, true);
            if($data && is_array($data)) {
                $addedAttributes = ArrayHelper::map($data, 'name', 'name');
            }
        }

        $list = $model->list ? json_decode($model->list, true) : ($allColumns ? array_flip($allColumns) : []);

        $list['admin_number']['name'] = 'No';
        $list['admin_number']['description'] = 'Порядковый номер';

        $list['admin_checkbox']['name'] = 'Чекбокс';
        $list['admin_checkbox']['description'] = 'Выбрать строку';

        if($modelInstance) {
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

        $model->list = $list;

        $publicStaticMethods = json_encode($model->model_class ? self::getPublicMethods($model->model_class) : []);
        $publicSaveMethods = json_encode($model->model_class ? self::getPublicMethods($model->model_class, true) : []);

        return $this->render('update', compact([
            'model',
            'columns',
            'modelInstance',
            'requiredColumns',
            'addedAttributes',
            'allColumns',
            'publicStaticMethods',
            'publicSaveMethods'
        ]));
    }

    /**
     * Получаем все свойства класса
     * @return array
     */
    private static function getPublicProperties(string $className) : array
    {
        // Создаем объект ReflectionClass для текущего класса
        $reflection = new \ReflectionClass($className);

        // Получаем все публичные свойства
        $publicProperties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        // Извлекаем имена свойств в массив
        $propertyNames = [];
        foreach ($publicProperties as $property) {
            $propertyNames[] = $property->getName();
        }

        return $propertyNames;
    }

    /**
     * Получаем все методы класса
     * @param string $className Имя класса
     * @param bool $forSave Классы для сохранения
     * @return array
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
                if(!$forSave) {
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
        if($publicMethods) {
            $publicMethods = array_merge([null => '---'], $publicMethods);
        }

        return $publicMethods;
    }

    public function actionDelete($id)
    {
        AdminModel::updateAll(['view' => 0], ['id' => $id]);
        return $this->redirect(['index']);
    }
}
