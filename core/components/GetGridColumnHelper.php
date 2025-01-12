<?php
namespace Mitisk\Yii2Admin\core\components;

use yii\helpers\ArrayHelper;

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

        $columns = [];
        if($this->columns) {
            foreach ($this->columns as $column => $data) {
                if(ArrayHelper::getValue($data, 'on')) {
                    if($column == 'number') {
                        $columns[] = [
                            'header'=>'No',
                            'class' => 'yii\grid\SerialColumn'
                        ];
                    } elseif($column == 'actions') {
                        $template = [];
                        if(ArrayHelper::getValue($data, 'data')) {
                            foreach (ArrayHelper::getValue($data, 'data') as $name => $on) {
                                if($on) {
                                    $template[] = '{' . $name . '}';
                                }
                            }
                        }
                        $columns[] = [
                            'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
                            'template' => implode(' ', $template),
                            'urlCreator' => function ($action, $model, $key, $index) {

                                if ($action === 'view') {
                                    return 'view/?id=' . $key;
                                }

                                if ($action === 'update') {
                                    return 'update/?id=' . $key;
                                }
                                if ($action === 'delete') {
                                    return 'delete/?id=' . $key;
                                }
                            },
                            'buttonOptions' => ['class' => '']
                        ];
                    } else {
                        $columns[] = [
                            'attribute' => $column,
                        ];
                    }
                }
            }
        }

        return $columns;
    }


}