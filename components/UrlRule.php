<?php
namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminModel;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UrlRuleInterface;
use yii\base\BaseObject;

class UrlRule extends \yii\web\UrlRule
{
    /**
     * Parses the given request and returns the corresponding route and parameters.
     * @param UrlManager $manager the URL manager
     * @param Request $request the request component
     * @return array|boolean the parsing result. The route and the parameters are returned as an array.
     * If false, it means this rule cannot be used to parse this path info.
     */
    public function parseRequest($manager, $request)
    {
        $pathInfo = trim($request->pathInfo, '/');
        $parts = explode('/', $pathInfo);

        $alias = ArrayHelper::getValue($parts, 1, $parts[count($parts) - 1]);

        ArrayHelper::removeValue($parts, 'admin');
        ArrayHelper::removeValue($parts, $alias);

        $page = AdminModel::find()->where(['alias' => $alias, 'view' => 1])->one();

        if ($page) {
            $path = 'admin/core/';
            if($parts) {
                $path .= implode('/', $parts);
            }
            return [$path, ['model_class' => $page->model_class]];
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function createUrl($manager, $route, $params)
    {
        if($alias = ArrayHelper::getValue($params, 'page-alias')) {
            unset($params['page-alias']);

            $url = str_replace('core', $alias, $route) . '/';

            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $url .= '?' . $query;
            }

            return str_replace('index/', '', $url);
        }
        return false;
    }




}