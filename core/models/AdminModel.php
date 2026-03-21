<?php
namespace Mitisk\Yii2Admin\core\models;

use Yii;
use Mitisk\Yii2Admin\fields\Field;
use Mitisk\Yii2Admin\models\Settings;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\data\ActiveDataProvider;

/**
 * Общий объект модели администратора
 */
class AdminModel extends BaseObject
{
    /** @var \yii\db\BaseActiveRecord */
    protected $_model;

    /** @var string */
    protected $_modelClassName;

    /** @var \Mitisk\Yii2Admin\models\AdminModel */
    public $component;

    /** @var string */
    public $search;

    public function __construct($model)
    {
        $this->setModel($model);
        $this->setComponent();
        parent::__construct();
    }

    /**
     * Return model
     * @return \yii\db\BaseActiveRecord
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * Return reflection class
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    public function getReflectionClass()
    {
        return new \ReflectionClass($this->_modelClassName);
    }

    /**
     * Set model
     * @param \yii\db\BaseActiveRecord $model
     */
    public function setModel(\yii\db\BaseActiveRecord $model)
    {
        $this->_model = $model;
        $this->_modelClassName = get_class($model);
    }

    /**
     * Set component
     * @return void
     */
    private function setComponent()
    {
        $this->component = \Mitisk\Yii2Admin\models\AdminModel::find()
            ->where(['model_class' => $this->_modelClassName])
            ->andWhere(['view' => 1])
            ->one();

        if ($this->component === null) {
            throw new \Exception("Component not found for model class: " . $this->_modelClassName);
        }
    }

    /**
     * Return has settings
     * @return bool
     */
    public function hasSettings() : bool
    {
        if (Yii::$app->user->can('admin')) {
            return Settings::find()->where(['model_name' => $this->_modelClassName])->exists();
        }
        return false;
    }

    /**
     * Return name
     * @return string
     */
    public function getName()
    {
        if($admin_label = $this->component->admin_label) {
            if($this->_model->hasAttribute($admin_label)) {
                return $this->_model->{$admin_label};
            }
        }

        if ($this->_model) {
            return $this->_model->name ?? $this->_model->title ?? $this->component->name ?? null;
        }

        return $this->component->name ?? null;
    }

    /**
     * Return component name
     * @return string
     */
    public function getComponentName()
    {
        return $this->component->name;
    }

    /**
     * Return model class name
     * @return string
     */
    public function getModelName() : string
    {
        return $this->_modelClassName;
    }

    /**
     * @param string|null $toAction
     * @return array[]
     */
    public function getUrls(string|null $toAction = null): array
    {
        $return = [
            'index' => ['index', 'page-alias' => $this->component->alias],
            'create' => ['create', 'page-alias' => $this->component->alias],
            'update' => ['update', 'id' => $this->_model->id, 'page-alias' => $this->component->alias],
            'delete' => ['delete', 'id' => $this->_model->id, 'page-alias' => $this->component->alias],
        ];

        if ($toAction) {
            return ArrayHelper::getValue($return, $toAction, ['index', 'page-alias' => $this->component->alias]);
        }

        return $return;
    }

    public function canList() : bool
    {
        if (Yii::$app->user->can($this->getModelName() . '\view') || Yii::$app->user->can('admin')) {
            return true;
        }
        return false;
    }

    /**
     * Return can view
     * @return bool
     */
    public function canView() : bool
    {
        if (Yii::$app->user->can($this->getModelName() . '\view') || Yii::$app->user->can('admin')) {
            return filter_var($this->component->can_view, FILTER_VALIDATE_BOOLEAN);
        }
        return false;
    }

    /**
     * Return can create
     * @return boolean
     */
    public function canCreate() : bool
    {
        if (Yii::$app->user->can($this->getModelName() . '\create') || Yii::$app->user->can('admin')) {
            return filter_var($this->component->can_create, FILTER_VALIDATE_BOOLEAN);
        }
        return false;
    }

    /**
     * Return can update
     * @return bool
     */
    public function canUpdate() : bool
    {
        if (Yii::$app->user->can($this->getModelName() . '\update') || Yii::$app->user->can('admin')) {
            return filter_var($this->component->can_update, FILTER_VALIDATE_BOOLEAN);
        }
        return false;
    }

    /**
     * Return can delete
     * @return bool
     */
    public function canDelete() : bool
    {
        if (Yii::$app->user->can($this->getModelName() . '\delete') || Yii::$app->user->can('admin')) {
            return filter_var($this->component->can_delete, FILTER_VALIDATE_BOOLEAN);
        }
        return false;
    }

    /**
     * Return grid columns
     * @return array
     */
    public function getGridColumns()
    {
        $helper = new \Mitisk\Yii2Admin\core\components\GetGridColumnHelper(
            json_decode($this->component->list, true),
            json_decode($this->component->data, true),
            $this
        );

        return $helper->getColumns();
    }

    /**
     * Return detail view helper
     * @return array
     */
    public function getDetailView()
    {
        $helper = new \Mitisk\Yii2Admin\core\components\GetDetailViewHelper(
            json_decode($this->component->data, true),
            $this
        );
        return $helper->getColumnsData();
    }

    /**
     * Return form inputs
     * @return array
     */
    public function getFormFields()
    {
        $inputs = json_decode($this->component->data, true);
        $return = [];

        if($inputs) {
            foreach ($inputs as $input) {
                $field = new Field(['input' => $input, 'model' => $this]);
                $return[] = $field->getFormInput();
            }
        }
        return $return;
    }

    /**
     * Метод для поиска по всем полям модели.
     *
     * @param string|null $text Глобальный поисковой запрос
     * @param \yii\db\ActiveRecord|null $filterModel Модель с загруженными фильтрами колонок
     * @return ActiveDataProvider
     */
    public function search(
        $text = null,
        $filterModel = null
    ) {
        $query = $this->_model->find();

        $sortConfig = [];

        if (!empty($this->component->default_sort_attribute)) {
            $sortConfig = [
                'defaultOrder' => [
                    $this->component->default_sort_attribute => (int)$this->component->default_sort_direction
                ]
            ];
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => $sortConfig,
        ]);

        // Глобальный поиск
        if ($text && is_string($text)) {
            $searchValue = trim($text);
            if (!empty($searchValue)) {
                $query->andFilterWhere(
                    ['or', ...$this->buildSearchConditions($searchValue)]
                );
            }
        }

        // Фильтры колонок
        if ($filterModel !== null) {
            $this->applyColumnFilters($query, $filterModel);
        }

        return $dataProvider;
    }

    /**
     * Карта типов полей из конфига компонента: [attribute => type].
     * @return array
     */
    protected function getFieldTypeMap(): array
    {
        $map = [];
        $data = json_decode($this->component->data ?? '[]', true);
        if (is_array($data)) {
            foreach ($data as $cfg) {
                $name = $cfg['name'] ?? null;
                $type = $cfg['type'] ?? null;
                if ($name && $type) {
                    $map[$name] = $type;
                }
            }
        }
        return $map;
    }

    /**
     * Применяет фильтры отдельных колонок из filterModel к запросу.
     *
     * @param \yii\db\ActiveQuery $query
     * @param \yii\db\ActiveRecord $filterModel
     */
    protected function applyColumnFilters($query, $filterModel): void
    {
        $tableSchema = $filterModel->getTableSchema();
        if ($tableSchema === null) {
            return;
        }

        $fieldTypes = $this->getFieldTypeMap();

        foreach ($filterModel->attributes() as $attribute) {
            $value = $filterModel->$attribute;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $type = $fieldTypes[$attribute] ?? null;

            // UserField — поиск по name/username/email в таблице user
            if ($type === 'user') {
                $this->applyUserFilter($query, $attribute, $value);
                continue;
            }

            // FileField — есть/нет файлы
            if ($type === 'file') {
                $this->applyFileFilter($query, $attribute, $value, $filterModel);
                continue;
            }

            // DateField — оператор + дата
            if ($type === 'date') {
                $this->applyDateFilter($query, $attribute, $value, $tableSchema);
                continue;
            }

            // Стандартная логика для остальных (select, posted, text и т.д.)
            $column = $tableSchema->getColumn($attribute);
            if ($column === null) {
                continue;
            }

            if ($column->phpType === 'integer' || $column->phpType === 'double') {
                $query->andFilterWhere([$attribute => $value]);
            } else {
                $query->andFilterWhere(['like', $attribute, $value]);
            }
        }
    }

    /**
     * UserField: поиск по имени/логину/email пользователя.
     */
    protected function applyUserFilter($query, string $attribute, $value): void
    {
        $userTable = \Mitisk\Yii2Admin\models\AdminUser::tableName();
        $userIds = (new \yii\db\Query())
            ->select('id')
            ->from($userTable)
            ->where(['or',
                ['like', 'username', $value],
                ['like', 'email', $value],
                ['like', 'name', $value],
            ])
            ->column();

        if (!empty($userIds)) {
            $query->andWhere([$attribute => $userIds]);
        } else {
            $query->andWhere('0=1');
        }
    }

    /**
     * FileField: фильтр «есть файлы / нет файлов».
     */
    protected function applyFileFilter(
        $query,
        string $attribute,
        $value,
        $filterModel
    ): void {
        $fileTable = \Mitisk\Yii2Admin\models\File::tableName();
        $modelClass = get_class($filterModel);
        $tableName = $filterModel->tableName();

        $subQuery = (new \yii\db\Query())
            ->select('1')
            ->from($fileTable)
            ->where([
                'class_name' => $modelClass,
                'field_name' => $attribute,
            ])
            ->andWhere($fileTable . '.item_id = ' . $tableName . '.id')
            ->limit(1);

        if ($value === '1') {
            $query->andWhere(['exists', $subQuery]);
        } else {
            $query->andWhere(['not exists', $subQuery]);
        }
    }

    /**
     * DateField: фильтр с оператором (=, >=, <=).
     * Поддерживает хранение даты как timestamp (int) и как строку.
     */
    protected function applyDateFilter(
        $query,
        string $attribute,
        $value,
        $tableSchema
    ): void {
        $ops = \Yii::$app->request->get('_df_op', []);
        $op = $ops[$attribute] ?? '=';
        if (!in_array($op, ['=', '>=', '<='], true)) {
            $op = '=';
        }

        $column = $tableSchema->getColumn($attribute);
        if ($column === null) {
            return;
        }

        $isInt = in_array(
            $column->type,
            ['integer', 'bigint', 'smallint'],
            true
        );

        $ts = strtotime($value);
        if ($ts === false) {
            return;
        }

        if ($isInt) {
            // Timestamp в int — сравниваем числа
            if ($op === '=') {
                $dayStart = strtotime(date('Y-m-d', $ts));
                $dayEnd = $dayStart + 86400;
                $query->andWhere([
                    'and',
                    ['>=', $attribute, $dayStart],
                    ['<', $attribute, $dayEnd],
                ]);
            } else {
                $query->andWhere([$op, $attribute, $ts]);
            }
        } else {
            // Строковая дата (datetime, date, varchar)
            $dateStr = date('Y-m-d', $ts);
            if ($op === '=') {
                $query->andFilterWhere([
                    'like', $attribute, $dateStr,
                ]);
            } elseif ($op === '>=') {
                $query->andWhere(
                    ['>=', $attribute, $dateStr . ' 00:00:00']
                );
            } else {
                $query->andWhere(
                    ['<=', $attribute, $dateStr . ' 23:59:59']
                );
            }
        }
    }

    /**
     * Генерирует массив условий для поиска по всем полям модели.
     *
     * @param string $searchValue Значение для поиска
     * @return array Массив условий
     */
    protected function buildSearchConditions($searchValue)
    {
        $conditions = [];
        foreach ($this->_model->attributes() as $attribute) {
            $conditions[] = ['like', $attribute, $searchValue];
        }
        return $conditions;
    }

    public function afterSave() : bool
    {
        $inputs = json_decode($this->component->data, true);

        if($inputs) {
            foreach ($inputs as $input) {
                $field = new Field(['input' => $input, 'model' => $this]);
                if (!$field->afterSave()) {
                    return false;
                }
            }
        }
        return true;
    }

    public function beforeDelete() : bool
    {
        $inputs = json_decode($this->component->data, true);

        if($inputs) {
            foreach ($inputs as $input) {
                $field = new Field(['input' => $input, 'model' => $this]);
                if (!$field->beforeDelete()) {
                    return false;
                }
            }
        }
        return true;
    }
}