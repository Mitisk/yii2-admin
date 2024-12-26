<?php

namespace Mitisk\Yii2Admin;

/**
 * admin module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'Mitisk\Yii2Admin\controllers';

    /**
     * @var bool
     */
    public $checkAccessPermissionAdministrateRbac = true;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
    }
}
