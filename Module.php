<?php

namespace Mitisk\Yii2Admin;

use yii\base\BootstrapInterface;

/**
 * admin module definition class
 */
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

    //public $mainLayout = '@app/vendor/Mitisk/Yii2Admin/views/layouts/admin.php';


    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param \app\components\Application $app
     */
    public function bootstrap($app)
    {
        $app->getUrlManager()->addRules([
            ['class' => \Mitisk\Yii2Admin\components\UrlRule::class],
            '<module:(partner|cabinet|message|admin)>/<controller:\w+>' => '<module>/<controller>/index',
            '<module>/<controller:\w+>/<action:\w+>' => '<module>/<controller>/<action>',
        ], false);
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
    }
}