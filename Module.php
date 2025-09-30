<?php

namespace Mitisk\Yii2Admin;

use Yii;
use kak\rbac\components\AccessControl;
use Mitisk\Yii2Admin\components\PermissionConst;
use yii\base\BootstrapInterface;

class Module extends \yii\base\Module implements BootstrapInterface
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'Mitisk\Yii2Admin\controllers';

    /**
     * @var bool
     */
    public $checkAccessPermissionAdministrateRbac = true;

    public $controllerMap = ['core' => 'Mitisk\Yii2Admin\core\controllers\AdminController'];

    public $layout = '@Mitisk/Yii2Admin/views/layouts/main.php';


    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param \app\components\Application $app
     */
    public function bootstrap($app)
    {
        $app->getUrlManager()->addRules([
            [
                'class' => \Mitisk\Yii2Admin\components\UrlRule::class,
                'pattern' => 'admin/<controller:\w+>/<action:\w+>', // Шаблон URL
                'route' => 'admin/<controller>/<action>',          // Маршрут для обработки
            ],

            'admin/login' => 'admin/default/login',  // Маршрут для страницы входа
            'admin/logout' => 'admin/default/logout', // Маршрут для выхода

            'admin/<controller:\w+>' => 'admin/<controller>/index', // Только для модуля admin
            'admin/<controller:\w+>/<action:\w+>' => 'admin/<controller>/<action>', // Только для модуля admin

        ], false);
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        Yii::$app->errorHandler->errorAction = 'admin/default/error';

        \Yii::setAlias('@Mitisk/Yii2Admin', __DIR__);

        if (Yii::$app->user->isGuest) {
            $currentRoute = trim(Yii::$app->request->getPathInfo(), '/');

            if ($currentRoute !== 'admin/login') {
                // Перенаправление на страницу входа
                Yii::$app->getResponse()->redirect('/admin/login/')->send();
                Yii::$app->end();
            }

        }
    }
}