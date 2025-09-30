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

    /**
     * Return can view
     * @return bool
     */
    public function canView() : bool
    {
        return filter_var($this->component->can_view, FILTER_VALIDATE_BOOLEAN);
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
     * @param string $text Поисковой запрос
     * @return ActiveDataProvider
     */
    public function search($text)
    {
        $query = $this->_model->find();

        // Создаем провайдер данных
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        // Загружаем параметры формы
        if (!$text || ($text && !is_string($text))) {
            return $dataProvider;
        }

        // Добавляем условие поиска по всем полям
        $searchValue = trim($text);

        if (!empty($searchValue)) {
            $query->andFilterWhere(['or', ...$this->buildSearchConditions($searchValue)]);
        }

        return $dataProvider;
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