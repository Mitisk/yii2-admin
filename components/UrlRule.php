<?php
namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminModel;
use Yii;
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

        $alias = $parts[count($parts) - 1];

        $page = AdminModel::find()->where(['alias' => $alias,'view' => 1])->one();

        if ($page) {
            return ['admin/core', ['model_class' => $page->model_class]];
        }

        return false;
    }

}