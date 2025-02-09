<?php
namespace Mitisk\Yii2Admin\core\models;

use Mitisk\Yii2Admin\fields\Field;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

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
     * Return can create
     * @return boolean
     */
    public function canCreate()
    {
        return filter_var($this->component->can_create, FILTER_VALIDATE_BOOLEAN);
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
}