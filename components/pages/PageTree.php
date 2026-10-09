<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\pages;

/**
 * Дерево из плоского списка объектов с `id`, `parent_id`, `sort`.
 * Пригодно и для AR Page, и для простых объектов (тесты). Циклы и сироты
 * не роняют построение: сирота выводится корнем, цикл — никуда.
 */
final class PageTree
{
    /**
     * @param iterable<object> $items
     * @return list<array{item: object, children: list<array>, depth: int}>
     */
    public static function build(iterable $items): array
    {
        $byParent = [];
        $ids = [];
        foreach ($items as $item) {
            $ids[(int)$item->id] = true;
            $byParent[$item->parent_id === null ? 0 : (int)$item->parent_id][] = $item;
        }
        foreach ($byParent as &$group) {
            usort($group, static fn(object $a, object $b): int => [(int)$a->sort, (int)$a->id] <=> [(int)$b->sort, (int)$b->id]);
        }
        unset($group);
        // Сироты (родитель не в списке) становятся корнями
        $roots = $byParent[0] ?? [];
        foreach ($byParent as $parentId => $group) {
            if ($parentId !== 0 && !isset($ids[$parentId])) {
                array_push($roots, ...$group);
            }
        }
        return self::nest($roots, $byParent, 0, []);
    }

    /**
     * @param list<object> $nodes
     * @param array<int, list<object>> $byParent
     * @param array<int, true> $seen
     * @return list<array{item: object, children: list<array>, depth: int}>
     */
    private static function nest(array $nodes, array $byParent, int $depth, array $seen): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $id = (int)$node->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = [
                'item' => $node,
                'depth' => $depth,
                'children' => self::nest($byParent[$id] ?? [], $byParent, $depth + 1, $seen),
            ];
        }
        return $out;
    }

    /**
     * @param list<array{item: object, children: list<array>, depth: int}> $tree
     * @return list<array{item: object, depth: int}>
     */
    public static function flatten(array $tree): array
    {
        $out = [];
        foreach ($tree as $node) {
            $out[] = ['item' => $node['item'], 'depth' => $node['depth']];
            array_push($out, ...self::flatten($node['children']));
        }
        return $out;
    }

    /**
     * Id всех потомков узла; при цикле каждый узел учитывается один раз.
     *
     * @param iterable<object> $items
     * @return list<int>
     */
    public static function descendantIds(iterable $items, int $id): array
    {
        $byParent = [];
        foreach ($items as $item) {
            if ($item->parent_id !== null) {
                $byParent[(int)$item->parent_id][] = (int)$item->id;
            }
        }
        $out = [];
        $stack = [$id];
        $seen = [$id => true];
        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($byParent[$current] ?? [] as $child) {
                if (!isset($seen[$child])) {
                    $seen[$child] = true;
                    $out[] = $child;
                    $stack[] = $child;
                }
            }
        }
        sort($out);
        return $out;
    }
}
