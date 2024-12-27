<?php
namespace Mitisk\Yii2Admin\core\components;

/**
 * Компонент для получения колонок в листинге
 *
 * @author DIGIMATIX <office@digimatix.ru>
 * @since 2.0
 */
class GetGridColumnHelper extends \yii\base\BaseObject
{
    /**
     * @var array
     */
    public $columns = [];

    /**
     * @var array
     */
    public $columnsData = [];

    /**
     * @param array $columns Массив колонок из AdminModel
     * @param array $columnsData Массив настроек колоной из AdminModel
     */
    public function __construct($columns = [], $columnsData = [])
    {
        $this->columns = $columns;
        $this->columnsData = $columnsData;
        parent::__construct();
    }

    /**
     * @return array Массив колонок для подстановки в GridView
     */
    public function getColumns() {


        return [
            [
                'header'=>'No',
                'class' => 'yii\grid\SerialColumn'
            ],
            'name',
            'description',
            'ruleName' => [
                'attribute' => 'ruleName',
                'filter' => [],
            ],
            [
                'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
                'buttonOptions' => ['class' => '']
            ],
        ];
    }


}