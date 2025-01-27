<?php
namespace Mitisk\Yii2Admin\core\components;

use yii\helpers\ArrayHelper;

/**
 * Компонент для получения колонок в листинге
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
     * @param array $columnsData Массив настроек колонок из AdminModel
     */
    public function __construct(array $columns = [], array $columnsData = [])
    {
        parent::__construct();
        $this->columns = $columns;
        $this->columnsData = $columnsData;
    }

    /**
     * @return array Массив колонок для подстановки в GridView
     */
    public function getColumns(): array
    {
        $columns = [];
        foreach ($this->columns as $column => $data) {
            if (ArrayHelper::getValue($data, 'on')) {
                $columns[] = $this->createColumn($column, $data);
            }
        }
        return $columns;
    }

    /**
     * Создаёт колонку на основе типа и данных
     *
     * @param string $column Название колонки
     * @param array $data Данные колонки
     * @return array Колонка для GridView
     */
    private function createColumn(string $column, array $data): array
    {
        switch ($column) {
            case 'number':
                return [
                    'header' => 'No',
                    'class' => 'yii\grid\SerialColumn',
                ];
            case 'actions':
                return $this->createActionColumn($data);
            default:
                return [
                    'attribute' => $column,
                ];
        }
    }

    /**
     * Создаёт колонку действий на основе данных
     *
     * @param array $data Данные для колонки действий
     * @return array Колонка действий для GridView
     */
    private function createActionColumn(array $data): array
    {
        $template = $this->getActionTemplate($data);
        return [
            'class' => 'Mitisk\Yii2Admin\widgets\ActionColumn',
            'template' => implode(' ', $template),
            'urlCreator' => function ($action, $model, $key) {
                return $this->createActionUrl($action, $key);
            },
            'buttonOptions' => ['class' => ''],
        ];
    }

    /**
     * Создаёт массив шаблонов для колонки действий
     *
     * @param array $data Данные для колонки действий
     * @return array Массив шаблонов
     */
    private function getActionTemplate(array $data): array
    {
        $template = [];
        if (ArrayHelper::getValue($data, 'data')) {
            foreach (ArrayHelper::getValue($data, 'data') as $name => $on) {
                if ($on) {
                    $template[] = '{' . $name . '}';
                }
            }
        }
        return $template;
    }

    /**
     * Создаёт URL для действий
     *
     * @param string $action Действие
     * @param mixed $key Ключ модели
     * @return string|null Возвращает URL или null, если действие неизвестно
     */
    private function createActionUrl(string $action, $key): ?string
    {
        switch ($action) {
            case 'view':
                return 'view/?id=' . $key;
            case 'update':
                return 'update/?id=' . $key;
            case 'delete':
                return 'delete/?id=' . $key;
            default:
                return null;
        }
    }

}
