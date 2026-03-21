<?php

namespace Mitisk\Yii2Admin\widgets;

use yii\grid\Column;
use yii\helpers\Html;

class GridView extends \yii\grid\GridView
{
    public $tableOptions = ['class' => 'modern-table'];

    public $headerRowOptions = [];

    public $rowOptions = [];

    /** @var bool Скрывать строку фильтров, если нет активной фильтрации */
    public $collapsibleFilters = true;

    public $layout = "{items}\n"
        . '<div class="pagination-wrapper">'
        . "{summary}\n{pager}</div>";

    public $summaryOptions = ['class' => 'text-muted'];

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->collapsibleFilters && $this->filterModel !== null) {
            $this->registerFilterToggleJs();
        }
        parent::run();
    }

    /**
     * Renders the data models for the grid view.
     * @return string the HTML code of table
     */
    public function renderItems(): string
    {
        $caption = $this->renderCaption();
        $columnGroup = $this->renderColumnGroup();
        $tableHeader = $this->showHeader
            ? $this->renderTableHeader()
            : false;
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

        $table = Html::tag(
            'table',
            implode("\n", $content),
            $this->tableOptions
        );

        return '<div class="table-card">'
            . '<div class="table-responsive">'
            . $table
            . '</div></div>';
    }

    /**
     * Renders the table header.
     * @return string the rendering result.
     */
    public function renderTableHeader(): string
    {
        $cells = [];
        foreach ($this->columns as $column) {
            /* @var $column Column */
            $cells[] = $column->renderHeaderCell();
        }

        $content = Html::tag(
            'tr',
            implode("\n", $cells),
            $this->headerRowOptions
        );

        if ($this->filterPosition === self::FILTER_POS_HEADER) {
            $content = $this->renderFilters() . $content;
        } elseif ($this->filterPosition === self::FILTER_POS_BODY) {
            $content .= $this->renderFilters();
        }

        return "<thead>\n" . $content . "\n</thead>";
    }

    /**
     * @inheritdoc
     */
    public function renderFilters(): string
    {
        if ($this->filterModel === null) {
            return '';
        }

        $cells = [];
        foreach ($this->columns as $column) {
            /* @var $column Column */
            $cells[] = $column->renderFilterCell();
        }

        $options = $this->filterRowOptions;

        if ($this->collapsibleFilters && !$this->hasActiveFilters()) {
            $existing = $options['style'] ?? '';
            $options['style'] = 'display:none;' . $existing;
        }

        return Html::tag('tr', implode('', $cells), $options);
    }

    /**
     * Renders the table body.
     * @return string the rendering result.
     */
    public function renderTableBody(): string
    {
        $models = array_values($this->dataProvider->getModels());
        $keys = $this->dataProvider->getKeys();
        $rows = [];
        foreach ($models as $index => $model) {
            $key = $keys[$index];
            if ($this->beforeRow !== null) {
                $row = call_user_func(
                    $this->beforeRow,
                    $model, $key, $index, $this
                );
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }

            $rows[] = $this->renderTableRow($model, $key, $index);

            if ($this->afterRow !== null) {
                $row = call_user_func(
                    $this->afterRow,
                    $model, $key, $index, $this
                );
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
        }

        if (empty($rows) && $this->emptyText !== false) {
            $colspan = count($this->columns);

            return "<tbody>\n<tr><td colspan=\"$colspan\">"
                . $this->renderEmpty()
                . "</td></tr>\n</tbody>";
        }

        return "<tbody>\n" . implode("\n", $rows) . "\n</tbody>";
    }

    /**
     * Renders a table row with the given data model and key.
     * @param mixed $model the data model to be rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data model among the model array returned by [[dataProvider]].
     * @return string the rendering result
     */
    public function renderTableRow($model, $key, $index): string
    {
        $cells = [];
        /* @var $column Column */
        foreach ($this->columns as $column) {
            $cells[] = $column->renderDataCell(
                $model, $key, $index
            );
        }

        if ($this->rowOptions instanceof \Closure) {
            $options = call_user_func(
                $this->rowOptions,
                $model, $key, $index, $this
            );
        } else {
            $options = $this->rowOptions;
        }
        $options['data-key'] = is_array($key)
            ? json_encode($key)
            : (string) $key;

        return Html::tag('tr', implode("\n", $cells), $options);
    }

    /**
     * Renders the pager.
     * @return string the rendering result
     */
    public function renderPager(): string
    {
        $pagination = $this->dataProvider->getPagination();
        if ($pagination === false
            || $this->dataProvider->getCount() <= 0
        ) {
            return '';
        }
        /* @var $class \Mitisk\Yii2Admin\widgets\LinkPager */
        $pager = $this->pager;

        $pager['pagination'] = $pagination;
        $pager['view'] = $this->getView();

        return LinkPager::widget($pager);
    }

    /**
     * Проверяет, есть ли активные (непустые) значения фильтров.
     * @return bool
     */
    protected function hasActiveFilters(): bool
    {
        if ($this->filterModel === null) {
            return false;
        }
        foreach ($this->filterModel->attributes() as $attr) {
            $val = $this->filterModel->$attr;
            if ($val !== null && $val !== '' && $val !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Регистрирует JS для кнопки .js-toggle-filters.
     */
    protected function registerFilterToggleJs(): void
    {
        $gridId = $this->options['id'];
        $active = $this->hasActiveFilters() ? 'true' : 'false';
        $js = <<<JS
(function(){
    var gridId = '{$gridId}';
    var active = {$active};
    var btn = document.querySelector('.js-toggle-filters');
    if (!btn) return;
    if (active) btn.classList.add('active');
    btn.addEventListener('click', function() {
        var row = document.querySelector('#' + gridId + '-filters');
        if (!row) return;
        var hidden = row.style.display === 'none';
        row.style.display = hidden ? '' : 'none';
        btn.classList.toggle('active', hidden);
    });
})();
JS;
        $this->getView()->registerJs($js);
    }
}
