<?php
namespace Mitisk\Yii2Admin\components;

use Yii;
use yii\helpers\Url;

/**
 * Class MenuHelper
 *
 * Готовит массив меню: фильтрует по RBAC, нормализует ссылки, вычисляет активность пунктов.
 * Активность: точное совпадение для «широких» путей ("/", baseUrl, корень сегмента),
 * иначе — равенство или префикс по границе сегмента; активность поднимается к родителям.
 */
final class MenuHelper
{
    /**
     * Строит меню: применяет фильтрацию доступа, нормализует href и помечает активные пункты.
     *
     * @param array $items Входной массив пунктов меню (text, href, rule, children и т.д.)
     * @return array Массив с добавленными полями _href_path и _active
     */
    public static function build(array $items): array
    {
        $request = Yii::$app->request;

        // Текущий путь в рамках приложения/модуля
        $base = rtrim($request->baseUrl, '/');          // напр. "/admin"
        $pi = '/' . ltrim($request->pathInfo, '/');     // напр. "/settings" или "/test/view"
        $currentPath = self::normalizePath(($base ?: '') . $pi); // напр. "/admin/settings"

        // Широкие корни: baseUrl и корень первого сегмента текущего пути
        $basePath = self::normalizePath($base ?: '/');  // напр. "/admin" или "/"
        $segmentRoot = self::segmentRoot($currentPath); // напр. "/admin"

        $items = self::filterByAccess($items);
        $items = self::normalizeHrefs($items);

        self::markActiveRecursive($items, $currentPath, $basePath, $segmentRoot);
        return $items;
    }

    /**
     * Фильтрация пунктов по RBAC-правам (user->can).
     *
     * @param array $items
     * @return array
     */
    private static function filterByAccess(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $rule = $item['rule'] ?? null;
            if ($rule && !Yii::$app->user->can($rule)) {
                continue;
            }
            if (!empty($item['children'])) {
                $item['children'] = self::filterByAccess($item['children']);
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Нормализует href каждого пункта и сохраняет путь в _href_path.
     * Поддерживает маршруты-массивы через Url::to().
     *
     * @param array $items
     * @return array
     */
    private static function normalizeHrefs(array $items): array
    {
        foreach ($items as &$item) {
            $href = $item['href'] ?? '';
            if (is_array($href)) {
                $href = Url::to($href); // корректная сборка URL для маршрутов/алиасов
            }
            $item['_href_path'] = self::pathFromUrl($href);
            if (!empty($item['children'])) {
                $item['children'] = self::normalizeHrefs($item['children']);
            }
        }
        unset($item);
        return $items;
    }

    /**
     * Возвращает нормализованный путь из URL, игнорируя query и fragment.
     *
     * @param string|null $url
     * @return string
     */
    private static function pathFromUrl(?string $url): string
    {
        if (!$url || $url === '#' || stripos($url, 'javascript:') === 0) {
            return '';
        }
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        return self::normalizePath($path);
    }

    /**
     * Нормализация пути: добавляет ведущий слэш, убирает хвостовой (кроме корня), приводит "/" к корню.
     *
     * @param string $path
     * @return string
     */
    private static function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        $path = '/' . ltrim($path, '/');
        return rtrim($path, '/');
    }

    /**
     * Возвращает корень первого сегмента пути: "/admin" для "/admin/anything".
     *
     * @param string $path
     * @return string
     */
    private static function segmentRoot(string $path): string
    {
        $trim = trim($path, '/');
        if ($trim === '') {
            return '/';
        }
        $first = strtok($trim, '/');
        return '/' . $first;
    }

    /**
     * Рекурсивная пометка активных пунктов: учитывает «широкие» пути ("/", baseUrl, segmentRoot)
     * как активные только по точному совпадению, иначе — равенство или префикс с границей сегмента.
     *
     * @param array $items
     * @param string $currentPath
     * @param string $basePath
     * @param string $segmentRoot
     * @return bool true, если внутри есть активные пункты
     */
    private static function markActiveRecursive(array &$items, string $currentPath, string $basePath, string $segmentRoot): bool
    {
        $anyActive = false;

        foreach ($items as &$item) {
            $itemPath = $item['_href_path'] ?? '';
            $hasChildren = !empty($item['children']);

            $selfActive = false;
            if ($itemPath !== '') {
                $isBroad = ($itemPath === '/' || $itemPath === $basePath || $itemPath === $segmentRoot);
                if ($isBroad) {
                    // «Главная» ("/" или "/admin") активна только при точном попадании в этот адрес
                    $selfActive = ($currentPath === $itemPath);
                } else {
                    // Равенство или префикс с границей сегмента
                    $p = rtrim($itemPath, '/');
                    $selfActive = ($currentPath === $p) || str_starts_with($currentPath, $p . '/');
                }
            }

            $childActive = false;
            if ($hasChildren) {
                $childActive = self::markActiveRecursive($item['children'], $currentPath, $basePath, $segmentRoot);
            }

            $item['_active'] = $selfActive || $childActive;
            $anyActive = $anyActive || $item['_active'];
        }
        unset($item);

        return $anyActive;
    }
}
