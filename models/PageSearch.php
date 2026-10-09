<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\pages\PageTree;
use yii\data\ArrayDataProvider;

/**
 * Фильтр списка страниц. Без фильтра — дерево в порядке обхода (родитель, затем его
 * вложенные), с фильтром по статусу, шаблону или поиску — плоский список по адресу.
 *
 * Отдаёт ArrayDataProvider без пагинации и сортировки: порядок строк задаёт дерево,
 * а глубина каждой строки лежит в {@see $depths} для отступа в GridView.
 */
class PageSearch extends Page
{
    /** @var string Поиск по заголовку и адресу */
    public $q = '';

    /** @var array<int, int> id страницы => глубина в дереве (0 — корень; в плоском списке все 0) */
    public array $depths = [];

    public function rules(): array
    {
        return [[['q', 'status', 'template'], 'string']];
    }

    public function behaviors(): array
    {
        return [];
    }

    /** Список отфильтрован: строки идут плоским списком, без отступов дерева. */
    public function isFiltered(): bool
    {
        return (string)$this->q !== '' || (string)$this->status !== '' || (string)$this->template !== '';
    }

    /**
     * @param array<string, mixed> $params Параметры запроса (`PageSearch[q|status|template]`).
     */
    public function search(array $params): ArrayDataProvider
    {
        $this->load($params);
        // ?PageSearch[status][]=x и прочий мусор в фильтре — просто дерево без фильтра, а не 500
        if (!$this->validate()) {
            $this->q = $this->status = $this->template = '';
        }
        $query = Page::find()->light()->with('updater')->ordered();
        if (!$this->isFiltered()) {
            $rows = PageTree::flatten(PageTree::build($query->all()));
        } else {
            // В PostgreSQL LIKE различает регистр
            $like = Page::getDb()->driverName === 'pgsql' ? 'ilike' : 'like';
            $query->andFilterWhere(['status' => $this->status, 'template' => $this->template])
                ->andFilterWhere(['or', [$like, 'title', $this->q], [$like, 'path', $this->q]]);
            $rows = array_map(static fn(Page $p): array => ['item' => $p, 'depth' => 0], $query->orderBy(['path' => SORT_ASC])->all());
        }

        $models = [];
        $this->depths = [];
        foreach ($rows as $row) {
            $models[] = $row['item'];
            $this->depths[(int)$row['item']->id] = (int)$row['depth'];
        }
        return new ArrayDataProvider([
            'allModels' => $models,
            'key' => 'id',
            'pagination' => false,
            'sort' => false,
        ]);
    }
}
