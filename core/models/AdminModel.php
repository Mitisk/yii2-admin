<?php
namespace Mitisk\Yii2Admin\core\models;

use Yii;
use yii\base\BaseObject;

/**
 * Общий объект модели администратора
 */
class AdminModel extends BaseObject
{
    /** @var \yii\db\BaseActiveRecord */
    protected $_model;

    /** @var string */
    protected $_className;

    /** @var \Mitisk\Yii2Admin\models\AdminModel */
    public $component;

    public function __construct(\yii\db\BaseActiveRecord $model)
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
     * Set model
     * @param \yii\db\BaseActiveRecord $model
     */
    public function setModel(\yii\db\BaseActiveRecord $model)
    {
        $this->_model = $model;
        $this->_className = get_class($model);
    }

    /**
     * Set component
     * @return void
     */
    private function setComponent()
    {
        $this->component = \Mitisk\Yii2Admin\models\AdminModel::find()->where(['model_class' => $this->_className])->andWhere(['view' => 1])->one();
    }

    /**
     * Return name
     * @return string
     */
    public function getName()
    {
        return $this->component->name;
    }

    /**
     * Return grid columns
     * @return array
     */
    public function getGridColumns()
    {
        $helper = new \Mitisk\Yii2Admin\core\components\GetGridColumnHelper(
            json_decode($this->component->list, true),
            json_decode($this->component->data, true)
        );

        return $helper->getGridColumns();
    }
}