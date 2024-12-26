<?php

namespace Mitisk\Yii2Admin\widgets;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\grid\Column;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\i18n\Formatter;
use yii\widgets\LinkPager;

class GridView extends \yii\grid\GridView
{
    public $tableOptions = ['class' => 'wg-table table-all-roles'];

    public $rowOptions = ['class' => "roles-item"];

    public $contentOptions = ['class' => "body-text"];

    public $layout = "{items}\n<div class=\"divider\"></div><div class='flex items-center justify-between flex-wrap gap10'>{summary}\n{pager}</div>";

    public $summaryOptions = ['class' => 'text-tiny'];

    /**
     * Renders the data models for the grid view.
     * @return string the HTML code of table
     */
    public function renderItems()
    {
        $caption = $this->renderCaption();
        $columnGroup = $this->renderColumnGroup();
        $tableHeader = $this->showHeader ? $this->renderTableHeader() : false;
        $tableBody = $this->renderTableBody();

        $tableFooter = false;
        $tableFooterAfterBody = false;

        if ($this->showFooter) {
            if ($this->placeFooterAfterBody) {
                $tableFooterAfterBody = $this->renderTableFooter();
            } else {
                $tableFooter = $this->renderTableFooter();
            }
        }

        $content = array_filter([
            $caption,
            $columnGroup,
            $tableHeader,
            $tableFooter,
            $tableBody,
            $tableFooterAfterBody,
        ]);

        return Html::tag('div', implode("\n", $content), $this->tableOptions);
    }

    /**
     * Renders the table header.
     * @return string the rendering result.
     */
    public function renderTableHeader()
    {
        $cells = [];
        $content =  '';
        foreach ($this->columns as $column) {
            /* @var $column Column */
            $cells[] = $column->renderHeaderCell();
        }
        if($cells) {
            foreach ($cells as $cell) {
                if($cell == "#") {
                    $cell = 'No';
                }
                $content .= Html::tag('li', Html::tag('div', $cell, ['class' => 'body-title']), $this->headerRowOptions);
            }
        }

        /**
         * @todo render filters
         */
        /*if ($this->filterPosition === self::FILTER_POS_HEADER) {
            $content = $this->renderFilters() . $content;
        } elseif ($this->filterPosition === self::FILTER_POS_BODY) {
            $content .= $this->renderFilters();
        }*/

        return "<ul class='table-title flex gap20 mb-14'>\n" . $content . "\n</ul>";
    }

    /**
     * Renders the table body.
     * @return string the rendering result.
     */
    public function renderTableBody()
    {
        $models = array_values($this->dataProvider->getModels());
        $keys = $this->dataProvider->getKeys();
        $rows = [];
        foreach ($models as $index => $model) {
            $key = $keys[$index];
            if ($this->beforeRow !== null) {
                $row = call_user_func($this->beforeRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }

            $rows[] = $this->renderTableRow($model, $key, $index);

            if ($this->afterRow !== null) {
                $row = call_user_func($this->afterRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
        }

        if (empty($rows) && $this->emptyText !== false) {
            $colspan = count($this->columns);

            return "<ul class=\"flex flex-column\">\n<DIV><DIV colspan=\"$colspan\">" . $this->renderEmpty() . "</DIV></DIV>\n</ul>";
        }

        return "<ul class=\"flex flex-column\">\n" . implode("\n", $rows) . "\n</ul>";
    }

    /**
     * Renders a table row with the given data model and key.
     * @param mixed $model the data model to be rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data model among the model array returned by [[dataProvider]].
     * @return string the rendering result
     */
    public function renderTableRow($model, $key, $index)
    {
        $cells = [];
        /* @var $column Column */
        foreach ($this->columns as $column) {
            $cells[] = $column->renderDataCell($model, $key, $index);
        }
        if ($this->rowOptions instanceof \Closure) {
            $options = call_user_func($this->rowOptions, $model, $key, $index, $this);
        } else {
            $options = $this->rowOptions;
        }
        $options['data-key'] = is_array($key) ? json_encode($key) : (string) $key;

        $content =  '';
        if($cells) {
            foreach ($cells as $cell) {
                if($cell == end($cells)) {
                    $content .= Html::tag('div', $cell, ['class' => 'list-icon-function', 'style' => 'width: 110px;']);
                } else {
                    $content .= Html::tag('div', $cell, ['class' => 'body-text']);
                }

            }
        }


        return Html::tag('li', $content, $options);
    }

    /**
     * Renders the pager.
     * @return string the rendering result
     */
    public function renderPager()
    {
        $pagination = $this->dataProvider->getPagination();
        if ($pagination === false || $this->dataProvider->getCount() <= 0) {
            return '';
        }
        /* @var $class \Mitisk\Yii2Admin\widgets\LinkPager */
        $pager = $this->pager;
        //$class = ArrayHelper::remove($pager, 'class', LinkPager::className());

        $pager['pagination'] = $pagination;
        $pager['view'] = $this->getView();

        return \Mitisk\Yii2Admin\widgets\LinkPager::widget($pager);
    }

}