<?php
namespace Mitisk\Yii2Admin\core\components;

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
     * @param array $columns Массив настроек колонок из AdminModel
     */
    public function __construct(array $columns = [])
    {
        parent::__construct();
        $this->columns = $columns;
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

}
