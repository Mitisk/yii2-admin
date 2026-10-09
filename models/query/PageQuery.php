<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\models\query;

use Mitisk\Yii2Admin\enums\PageStatus;
use Mitisk\Yii2Admin\models\Page;
use Yii;
use yii\db\ActiveQuery;

/**
 * @method Page|null one($db = null)
 * @method Page[] all($db = null)
 */
class PageQuery extends ActiveQuery
{
    /** Опубликованные, у которых срок публикации наступил. */
    public function published(?int $now = null): static
    {
        $t = Page::tableName();
        return $this->andWhere([$t . '.[[status]]' => PageStatus::Published->value])
            ->andWhere(['or', [$t . '.[[published_at]]' => null], ['<=', $t . '.[[published_at]]', $now ?? time()]]);
    }

    /**
     * Что видит текущий посетитель: гость — опубликованные; администратор с `viewContent` —
     * ещё и черновики (архив не видит никто).
     */
    public function visible(): static
    {
        if (Yii::$app->has('adminBar') && Yii::$app->get('adminBar')->can('viewContent')) {
            return $this->andWhere(['<>', Page::tableName() . '.[[status]]', PageStatus::Archived->value]);
        }
        return $this->published();
    }

    public function byPath(string $path): static
    {
        return $this->andWhere([Page::tableName() . '.[[path]]' => trim($path, '/')]);
    }

    public function children(?int $parentId): static
    {
        return $this->andWhere([Page::tableName() . '.[[parent_id]]' => $parentId]);
    }

    public function roots(): static
    {
        return $this->children(null);
    }

    public function ordered(): static
    {
        $t = Page::tableName();
        return $this->orderBy([$t . '.[[sort]]' => SORT_ASC, $t . '.[[id]]' => SORT_ASC]);
    }

    /** Без тяжёлого `body` — для деревьев, карт и списков. */
    public function light(): static
    {
        $t = Page::tableName();
        return $this->select(array_map(
            static fn(string $c): string => $t . '.[[' . $c . ']]',
            ['id', 'parent_id', 'slug', 'path', 'title', 'template', 'status', 'published_at', 'sort', 'noindex', 'updated_at', 'updated_by']
        ));
    }
}
