<?php
namespace Mitisk\Yii2Admin\core\components;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Mitisk\Yii2Admin\fields\Field;
use yii\helpers\ArrayHelper;

/**
 * Компонент для получения вида
 */
class GetDetailViewHelper extends \yii\base\BaseObject
{
    /**
     * @var array
     */
    public $columns = [];

    /**
     * @var AdminModel
     */
    public $model;

    /**
     * @param array $columns Массив настроек колонок из AdminModel
     */
    public function __construct(array $columns = [], AdminModel $model = null)
    {
        parent::__construct();
        $this->columns = $columns;
        $this->model = $model;
    }

    /**
     * @return array Массив колонок для подстановки в DetailView
     */
    public function getColumns(): array
    {
        $columns = [];
        foreach ($this->columns as $column => $data) {
            if (ArrayHelper::getValue($data, 'name')) {
                $columns[] = ArrayHelper::getValue($data, 'name');
            }
        }
        return $columns;
    }

    public function getColumnsData(): array
    {
        $return = [];
        if($this->columns) {
            foreach ($this->columns as $input) {
                $field = new Field(['input' => $input, 'model' => $this->model]);

                if($field->getLabel() && $field->getViewData()) {
                    $return[] = [
                        'attribute' => $field->getLabel(),
                        'value' => $field->getViewData(),
                        'format' => 'raw'
                    ];
                }

            }
        }
        return $return;
    }

}
