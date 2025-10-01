<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin;

use Yii;
use yii\base\BootstrapInterface;
use Mitisk\Yii2Admin\models\AdminControllerMap;
use Mitisk\Yii2Admin\components\ExtAdminController;

final class Module extends \yii\base\Module implements BootstrapInterface
{
    public $controllerNamespace = 'Mitisk\Yii2Admin\controllers';
    public $checkAccessPermissionAdministrateRbac = true;

    // Базовая статическая карта НЕ теряется
    public $controllerMap = [
        'core' => 'Mitisk\Yii2Admin\core\controllers\AdminController',
    ];

    public $layout = '@Mitisk/Yii2Admin/views/layouts/main.php';

    public function init()
    {
        parent::init();

        // Без редиректов и завершения выполнения здесь
        Yii::$app->errorHandler->errorAction = 'admin/default/error';
        \Yii::setAlias('@Mitisk/Yii2Admin', __DIR__);
    }

    // Все динамические вещи и правила — здесь
    public function bootstrap($app)
    {
        // 1) Правила URL
        $app->getUrlManager()->addRules([
            // Явные маршруты логина/логаута
            'admin/login'  => 'admin/default/login',
            'admin/logout' => 'admin/default/logout',

            [
                'class' => \Mitisk\Yii2Admin\components\UrlRule::class,
                'pattern' => 'admin/<controller:\w+>/<action:\w+>', // Шаблон URL
                'route' => 'admin/<controller>/<action>',          // Маршрут для обработки
            ],

            // Общие шаблоны админки
            'admin/<controller:\w+>' => 'admin/<controller>/index',
            'admin/<controller:\w+>/<action:\w+>' => 'admin/<controller>/<action>',
        ], false);

        // 2) Динамическая карта контроллеров из БД
        try {
            $dbMap = $this->buildControllerMapFromDb();
            // ВАЖНО: слить, а не перезаписать, чтобы не потерять 'core'
            $this->controllerMap = array_merge($this->controllerMap, $dbMap);
        } catch (\Throwable $e) {
            Yii::warning('admin controllerMap load failed: ' . $e->getMessage(), __METHOD__);
        }
    }

    public function beforeAction($action)
    {
        // Редирект гостя — здесь
        // Исключаем экшен логина, чтобы не зациклиться
        if (Yii::$app->request->isConsoleRequest === false) {
            $route = $action->uniqueId; // например, 'admin/default/login'
            $isLogin = ($route === 'admin/default/login');
            if (Yii::$app->user->isGuest && !$isLogin) {
                Yii::$app->response->redirect(['/admin/default/login'])->send();
                return false;
            }
        }
        return parent::beforeAction($action);
    }

    private function buildControllerMapFromDb(): array
    {
        $map = [];
        $rows = AdminControllerMap::find()->where(['enabled' => 1])->all();

        foreach ($rows as $row) {
            $id = $row->controller_id;
            $class = $row->class;
            $cfg = is_array($row->config) ? $row->config : (json_decode((string)$row->config, true) ?: []);

            if (!class_exists($class)) {
                Yii::warning("Controller class not found: {$class}");
                continue;
            }
            if (!is_subclass_of($class, ExtAdminController::class)) {
                Yii::warning("Controller {$class} must extend ExtAdminController");
                continue;
            }
            $map[$id] = array_merge(['class' => $class], $cfg);
        }
        return $map;
    }
}
