<?php
namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminModel;
use yii\helpers\ArrayHelper;
use yii\web\UrlManager;
use yii\web\Request;

class UrlRule extends \yii\web\UrlRule
{
    /**
     * Parses the given request and returns the corresponding route and parameters.
     * @param UrlManager $manager the URL manager
     * @param Request $request the request component
     * @return array|false the parsing result. The route and the parameters are returned as an array.
     * If false, it means this rule cannot be used to parse this path info.
     */
    public function parseRequest($manager, $request): array|false
    {
        $pathInfo = trim($request->pathInfo, '/');
        $parts = explode('/', $pathInfo);
        $alias = ArrayHelper::getValue($parts, 1, end($parts));

        $parts = array_filter($parts, fn($part) => $part !== 'admin' && $part !== $alias); // Убираем 'admin' и alias

        $page = AdminModel::find()->where(['alias' => $alias, 'view' => 1])->one();

        if ($page) {
            $path = 'admin/core/';
            $path .= !empty($parts) ? implode('/', $parts) : ''; // Проверяем, не пуст ли массив

            return [$path, ['model_class' => $page->model_class, 'page-alias' => $alias]];
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function createUrl($manager, $route, $params): string|false
    {
        if ($alias = ArrayHelper::getValue($params, 'page-alias')) {
            unset($params['page-alias']);
            ArrayHelper::remove($params, 'model_class');

            $url = str_replace('core', $alias, $route) . '/';
            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $url .= '?' . $query;
            }

            return str_replace(['index/', 'index'], '', $url);
        } elseif (str_contains($route, '/core/')) {
            // Если page-alias отсутствует, строим стандартный URL
            $url = $route;

            $pathInfo = \Yii::$app->request->getPathInfo();
            $pathInfo = trim($pathInfo, '/');
            $pathinfoArr = explode('/', $pathInfo);

            if(isset($pathinfoArr[1])) {
                $url = str_replace('core', $pathinfoArr[1], $url);
            }

            // Добавляем параметры в виде query string
            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $url .= '?' . $query;
            }

            return str_replace(['index/', 'index'], '', $url);
        }

        return false;
    }
}
