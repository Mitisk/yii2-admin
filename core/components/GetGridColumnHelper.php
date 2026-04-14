<?php
namespace Mitisk\Yii2Admin\core\components;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Mitisk\Yii2Admin\fields\Field;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

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
     * @var AdminModel
     */
    public $model;

    /**
     * Пул пользовательских ссылок-кнопок компонента.
     *
     * @var array<int, array>
     */
    public array $linksPool = [];

    /**
     * @param array $columns Массив колонок из AdminModel
     * @param array $columnsData Массив настроек колонок из AdminModel
     */
    public function __construct(array $columns = [], array $columnsData = [], AdminModel $model = null)
    {
        parent::__construct();
        $this->columns = $columns;
        $this->columnsData = self::prepareData($columnsData);
        $this->model = $model;
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
            case 'admin_number':
                return [
                    'header' => 'No',
                    'class' => 'yii\grid\SerialColumn',
                ];
            case 'admin_checkbox':
                return [
                    'class' => 'yii\grid\CheckboxColumn',
                ];
            case 'admin_actions':
                return $this->createActionColumn($data);
            default:
                if (str_starts_with($column, 'admin_link_')) {
                    return $this->createLinkColumn($data);
                }
                if (ArrayHelper::getValue($this->columnsData, $column . '.type')) {

                    $field = new Field(['input' => ArrayHelper::getValue($this->columnsData, $column), 'model' => $this->model]);
                    return $field->getListData($column);
                } else {
                    return [
                        'attribute' => $column
                    ];
                }
        }
    }

    /**
     * Создаёт колонку со слотом пользовательских ссылок-кнопок.
     *
     * @param array $data Конфиг слота (items — массив uid ссылок).
     *
     * @return array Конфиг колонки для GridView.
     */
    private function createLinkColumn(array $data): array
    {
        $items    = ArrayHelper::getValue($data, 'items', []);
        $header   = (string)ArrayHelper::getValue($data, 'name', '');
        $pool     = $this->linksPool;

        return [
            'header' => Html::encode($header),
            'format' => 'raw',
            'value'  => static function ($model) use ($items, $pool) {
                if (!is_array($items) || empty($items)) {
                    return '';
                }
                $out = [];
                foreach ($items as $id) {
                    $link = LinkRenderer::findById($pool, (string)$id);
                    if ($link === null) {
                        continue;
                    }
                    $out[] = LinkRenderer::render($link, $model, 'list');
                }
                return '<div class="admin-link-btn-group">'
                    . implode(' ', $out) . '</div>';
            },
        ];
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
        /*if (ArrayHelper::getValue($data, 'data')) {
            foreach (ArrayHelper::getValue($data, 'data') as $name => $on) {
                if ($on) {
                    $template[] = '{' . $name . '}';
                }
            }
        }*/
        if ($this->model->canView()) {
            $template[] = '{view}';
        }
        if ($this->model->canUpdate()) {
            $template[] = '{update}';
        }
        if ($this->model->canDelete()) {
            $template[] = '{delete}';
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

    /**
     * Подготавливает данные для колонок
     *
     * @param array $data Данные колонок
     * @return array Подготовленные данные колонок
     */
    private function prepareData(array $data): array
    {
        $return = [];
        foreach ($data as $value) {
            if (ArrayHelper::getValue($value, 'name')) {
                $return[ArrayHelper::getValue($value, 'name')] = $value;
            }
        }
        return $return;
    }

}
