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
            if($model->load(\Yii::$app->request->post()) && $model->validate()) {
                if($model->save(false)) {
                    Yii::$app->session->setFlash('success', 'Компонент обновлен.');
                }
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

        $columns = array_keys($columns);
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
            $data = $model->data;
            $data = json_decode($model->data, true);
            if($data && is_array($data)) {
                $addedAttributes = ArrayHelper::map($data, 'name', 'name');
            }
        }

        $list = $model->list ? json_decode($model->list, true) : ($allColumns ? array_flip($allColumns) : []);

        $list['number']['name'] = 'No';
        $list['number']['description'] = 'Порядковый номер';

        if($modelInstance) {
            foreach ($allColumns as $column) {
                $list[$column] = ArrayHelper::getValue($list, $column, []);
                $list[$column]['name'] = $modelInstance->getAttributeLabel($column);
                $list[$column]['description'] = $column;
            }
        }

        $list['actions']['name'] = 'Действия';
        $list['actions']['description'] = '<i class="icon-eye js-list-actions ' . (ArrayHelper::getValue($list, 'actions.data.view') ? 'active' : '') . '">'
            . Html::hiddenInput(Html::getInputName($model, 'list').'[actions][data][view]', ArrayHelper::getValue($list, 'actions.data.view', '0'))
            . '</i>
            <i class="icon-edit-3 js-list-actions ' . (ArrayHelper::getValue($list, 'actions.data.update') ? 'active' : '') . '">'
            . Html::hiddenInput(Html::getInputName($model, 'list').'[actions][data][update]', ArrayHelper::getValue($list, 'actions.data.update', '0'))
            . '</i>
            <i class="icon-trash-2 js-list-actions ' . (ArrayHelper::getValue($list, 'actions.data.delete') ? 'active' : '') . '">'
            . Html::hiddenInput(Html::getInputName($model, 'list').'[actions][data][delete]', ArrayHelper::getValue($list, 'actions.data.delete', '0'))
            . '</i>';

        $model->list = $list;

        return $this->render('update', compact(['model', 'columns', 'modelInstance', 'requiredColumns', 'addedAttributes', 'allColumns']));
    }

    public function actionDelete($id)
    {
        AdminModel::updateAll(['view' => 0], ['id' => $id]);
        return $this->redirect(['index']);
    }
}
