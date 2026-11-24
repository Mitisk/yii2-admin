<?php
namespace Mitisk\Yii2Admin\core\controllers;

use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\core\models\AdminModel;
use Mitisk\Yii2Admin\models\AdminModel as ComponentRecord;
use Yii;
use yii\db\BaseActiveRecord;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class AdminController extends BaseController
{
    /** @var string ClassName of model */
    protected $_modelName;

    /** @var string Шаблон листинга */
    public $actionIndexTemplate = 'index';

    /** @var string Шаблон формы создания */
    public $actionCreateTemplate = 'create';

    /** @var string Шаблон формы редактирования */
    public $actionUpdateTemplate = 'update';

    /** @var string Шаблон просмотра */
    public $actionViewTemplate = 'view';

    /** @var string Шаблон формы */
    public $formTemplate = '_form';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    // Просмотр списка и карточки
                    [
                        'actions' => ['index', 'view'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\view') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                    // Создание
                    [
                        'actions' => ['create'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\create') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                    // Обновление
                    [
                        'actions' => ['update', 'update-attribute'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\update') ||
                                Yii::$app->user->can('admin');
                        },
                    ],

                    // Удаление
                    [
                        'actions' => ['delete', 'batch-delete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return Yii::$app->user->can($this->_modelName . '\delete') ||
                                Yii::$app->user->can('admin');
                        },
                    ],
                    // Инструкция
                    [
                        'actions' => ['instruction'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'batch-delete' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        $this->_modelName = $this->resolveModelClass(Yii::$app->request->get('model_class'));
        return parent::beforeAction($action);
    }

    /**
     * model_class приходит из адреса (/admin/core/<action>/?model_class=...), поэтому принимаем
     * только класс активного компонента админки. Реестр проверяется до class_exists(),
     * чтобы произвольная строка не уходила в автозагрузчик.
     *
     * @param mixed $modelClass
     * @return string
     * @throws NotFoundHttpException
     */
    private function resolveModelClass($modelClass): string
    {
        if (!is_string($modelClass) || $modelClass === '') {
            throw new NotFoundHttpException('Раздел не найден.');
        }

        $registered = ComponentRecord::find()
            ->where(['model_class' => $modelClass, 'view' => 1])
            ->exists();

        if (!$registered
            || !class_exists($modelClass)
            || !is_subclass_of($modelClass, BaseActiveRecord::class)
        ) {
            throw new NotFoundHttpException('Раздел не найден.');
        }

        return $modelClass;
    }

    public function actionIndex()
    {
        /** @var AdminModel $model */
        $model = new AdminModel(
            \Yii::createObject(['class' => $this->_modelName])
        );

        $filterModel = $model->getModel();
        $filterModel->load(\Yii::$app->request->get());

        $globalSearch = ArrayHelper::getValue(
            \Yii::$app->request->get(),
            $filterModel->formName() . '.search'
        );

        $dataProvider = $model->search($globalSearch, $filterModel);

        return $this->render($this->actionIndexTemplate, [
            'model' => $model,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        /** @var AdminModel $model */
        $model = new AdminModel(\Yii::createObject(['class' => $this->_modelName]));

        if (!$model->canCreate()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        if (\Yii::$app->request->isPost) {
            $modelData = $model->getModel();
            if ($modelData->load(\Yii::$app->request->post()) && $modelData->save()) {
                AuditService::log('create', $modelData);
                if($model->afterSave()) {
                    Yii::$app->session->setFlash('success', 'Запись успешно добавлена');
                    return $this->redirect(['index']);
                }
            }

            Yii::$app->session->setFlash('error', 'Ошибка при добавлении записи: ' . implode(', ', $modelData->getFirstErrors()));
        }

        return $this->render($this->actionCreateTemplate, [
            'model' => $model,
            'formTemplate' => $this->formTemplate,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel();
        if ($model instanceof Response) {
            return $model;
        }
        if (!$model->canUpdate()) {
            throw new ForbiddenHttpException('Правка записей в этом разделе запрещена.');
        }

        if (\Yii::$app->request->isPost) {
            $modelData = $model->getModel();
            $oldAttributes = $modelData->getOldAttributes();
            if ($modelData->load(\Yii::$app->request->post()) && $modelData->save()) {
                $changed = [];
                foreach ($modelData->getAttributes() as $attr => $newVal) {
                    $oldVal = $oldAttributes[$attr] ?? null;
                    if (!AuditService::sameValue($oldVal, $newVal)) {
                        $changed[$attr] = $oldVal;
                    }
                }
                if ($changed) {
                    AuditService::log('update', $modelData, $changed);
                }
                if($model->afterSave()) {
                    Yii::$app->session->setFlash('success', 'Запись успешно обновлена');
                    return $this->redirect(['index']);
                }
            }

            Yii::$app->session->setFlash('error', 'Ошибка при обновлении записи: ' . implode(', ', $modelData->getFirstErrors()));
        }

        return $this->render($this->actionUpdateTemplate, [
            'model' => $model,
            'formTemplate' => $this->formTemplate,
        ]);
    }

    public function actionView($id)
    {
        $model = $this->findModel();
        if ($model instanceof Response) {
            return $model;
        }
        if (!$model->canView()) {
            throw new ForbiddenHttpException('Просмотр записей в этом разделе запрещён.');
        }

        return $this->render($this->actionViewTemplate, [
                'model' => $model,
            ]
        );
    }

    public function actionDelete()
    {
        $adminModel = $this->findModel();
        if ($adminModel instanceof Response) {
            return $adminModel;
        }
        if (!$adminModel->canDelete()) {
            throw new ForbiddenHttpException('Удаление записей в этом разделе запрещено.');
        }

        $arModel = $adminModel->getModel();
        if ($adminModel->beforeDelete() && $arModel->delete()) {
            AuditService::log('delete', $arModel);
            Yii::$app->session->setFlash('success', 'Запись успешно удалена');
        } else {
            Yii::$app->session->setFlash('error', 'Ошибка удаления');
        }

        return $this->redirect(Url::to(['index']));
    }

    public function actionBatchDelete()
    {
        $ids = Yii::$app->request->post('ids');
        $modelName = $this->_modelName;

        $component = new AdminModel(Yii::createObject(['class' => $modelName]));
        if (!$component->canDelete()) {
            throw new ForbiddenHttpException('Удаление записей в этом разделе запрещено.');
        }

        if (empty($ids) || !is_array($ids)) {
            Yii::$app->session->setFlash('error', 'Элементы не выбраны');
            return $this->redirect(['index', 'model_class' => $modelName]);
        }

        $count = 0;
        foreach ($ids as $id) {
            $record = $modelName::findOne($id);
            if ($record) {
                $adminModel = new AdminModel($record);
                if ($adminModel->beforeDelete() && $adminModel->getModel()->delete()) {
                    AuditService::log('delete', $record);
                    $count++;
                }
            }
        }

        Yii::$app->session->setFlash('success', "Удалено записей: $count");
        return $this->redirect(Url::to(['index', 'model_class' => $modelName]));
    }


    protected function findModel()
    {
        $modelName = $this->_modelName;
        $keys = [];

        foreach ($modelName::primaryKey() as $key) {
            if ($id = Yii::$app->request->get($key)) {
                $keys[$key] = $id;
            }
        }

        if (empty($keys)) {
            throw new \yii\web\NotFoundHttpException("Не удалось найти модель с таким идентификатором.");
        }

        $model = $modelName::find()->andWhere($keys)->one();
        if ($model !== null) {
            return new AdminModel($model);
        }

        Yii::$app->session->setFlash('error', 'Такая страница не найдена');

        return $this->redirect(['index']);
    }

    /**
     * Переключатель «Активно/Неактивно» в списке (AJAX, пакетно).
     *
     * Принимает items[]{id, model, attribute, value}. Меняется только запись модели этого
     * раздела и только поле типа posted из его формы (не readonly, видимое пользователю
     * по roles и безопасное в rules() модели). Значение проходит валидацию атрибута,
     * изменение пишется в аудит.
     *
     * @return array{success: bool, count: int, errors: string[], message?: string}
     * @throws ForbiddenHttpException раздел запрещает правку (admin_actions) или нет права
     */
    public function actionUpdateAttribute()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $adminModel = new AdminModel(Yii::createObject(['class' => $this->_modelName]));
        if (!$adminModel->canUpdate()) {
            throw new ForbiddenHttpException('Правка записей в этом разделе запрещена.');
        }

        $items = Yii::$app->request->post('items');
        if (empty($items) || !is_array($items)) {
            return ['success' => false, 'count' => 0, 'errors' => [], 'message' => 'Нет данных для обновления'];
        }

        $toggleable = $adminModel->getListToggleAttributes();
        $count = 0;
        $errors = [];

        foreach ($items as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            $modelClass = is_array($item) ? str_replace('\\\\', '\\', (string)($item['model'] ?? '')) : '';
            $attribute = is_array($item) ? ($item['attribute'] ?? null) : null;
            $value = is_array($item) ? ($item['value'] ?? null) : null;

            if (!is_scalar($id) || (string)$id === '' || !is_string($attribute) || !is_scalar($value)) {
                $errors[] = 'Неверные данные для обновления';
                continue;
            }
            if ($modelClass !== $this->_modelName) {
                $errors[] = "Запись ID {$id} не относится к этому разделу";
                continue;
            }
            if (!in_array($attribute, $toggleable, true)) {
                $errors[] = "Поле «{$attribute}» нельзя менять из списка";
                continue;
            }

            /** @var \yii\db\BaseActiveRecord|null $record */
            $record = $modelClass::findOne($id);
            if ($record === null) {
                $errors[] = "Запись ID {$id} не найдена";
                continue;
            }
            if (!$record->isAttributeSafe($attribute)) {
                $errors[] = "Поле «{$attribute}» нельзя менять из списка";
                continue;
            }

            $old = $record->getAttribute($attribute);
            $record->setAttribute($attribute, $value);
            if (!$record->validate([$attribute])) {
                $errors[] = "ID {$id}: " . implode(', ', $record->getErrorSummary(false));
                continue;
            }
            if ($record->save(false, [$attribute]) === false) {
                $errors[] = "Ошибка сохранения ID {$id}";
                continue;
            }

            $count++;
            if (!AuditService::sameValue($old, $record->getAttribute($attribute))) {
                AuditService::log('update', $record, [$attribute => $old]);
            }
        }

        return [
            'success' => $errors === [],
            'count' => $count,
            'errors' => $errors,
        ];
    }

    /**
     * Показывает инструкцию или форму редактирования
     */
    public function actionInstruction()
    {
        $modelClass = $this->_modelName;
        $info = \Mitisk\Yii2Admin\models\AdminModelInfo::findOne(['model_class' => $modelClass]);

        // Если это запрос на редактирование (или сохранение)
        if (Yii::$app->request->get('edit') || Yii::$app->request->isPost) {
            // Проверка прав на редактирование
            if (!Yii::$app->user->can('superAdminRole')) {
                throw new \yii\web\ForbiddenHttpException('У вас нет прав на редактирование инструкции.');
            }

            if (!$info) {
                $info = new \Mitisk\Yii2Admin\models\AdminModelInfo();
                $info->model_class = $modelClass;
            }

            if ($info->load(Yii::$app->request->post()) && $info->save()) {
                Yii::$app->session->setFlash('success', 'Инструкция сохранена');
                return $this->redirect(['instruction']);
            }

            return $this->render('instruction_edit', [
                'model' => $info,
            ]);
        }

        // Просмотр
        if (!$info) {
            // Если инструкции нет
            if (Yii::$app->user->can('superAdminRole')) {
                // Супер-админа редиректим на создание
                return $this->redirect(['instruction', 'edit' => 1]);
            } else {
                // Обычному пользователю показываем 404 или ничего (хотя по ТЗ кнопка должна быть скрыта)
                throw new \yii\web\NotFoundHttpException("Инструкция не найдена");
            }
        }

        // Если AJAX запрос (как в модалке)
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('instruction', [
                'model' => $info,
                'canEdit' => Yii::$app->user->can('superAdminRole'),
            ]);
        }

        // Если обычный запрос (прямая ссылка)
        return $this->render('instruction', [
            'model' => $info,
            'canEdit' => Yii::$app->user->can('superAdminRole'),
        ]);
    }
}
