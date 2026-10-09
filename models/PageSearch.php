<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\pages\PageTree;

/**
 * Фильтр дерева страниц. Фильтр по статусу/шаблону/поиску даёт плоский список,
 * без фильтра — дерево с отступами.
 */
class PageSearch extends Page
{
    /** @var string Поиск по заголовку и адресу */
    public $q = '';

    public function rules(): array
    {
        return [[['q', 'status', 'template'], 'string']];
    }

    public function behaviors(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array{item: Page, depth: int}>
     */
    public function search(array $params): array
    {
        $this->load($params, '');
        // ?status[]=x и прочий мусор в фильтре — просто дерево без фильтра, а не 500
        if (!$this->validate()) {
            $this->q = $this->status = $this->template = '';
        }
        $query = Page::find()->light()->with('updater')->ordered();
        $filtered = (string)$this->q !== '' || (string)$this->status !== '' || (string)$this->template !== '';
        if (!$filtered) {
            return PageTree::flatten(PageTree::build($query->all()));
        }
        // В PostgreSQL LIKE различает регистр
        $like = Page::getDb()->driverName === 'pgsql' ? 'ilike' : 'like';
        $query->andFilterWhere(['status' => $this->status, 'template' => $this->template])
            ->andFilterWhere(['or', [$like, 'title', $this->q], [$like, 'path', $this->q]]);
        return array_map(static fn(Page $p): array => ['item' => $p, 'depth' => 0], $query->orderBy(['path' => SORT_ASC])->all());
    }
}
