<?php
namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminUser;
use Yii;
use yii\web\Controller;

class BaseController extends Controller
{
    public $enableCsrfValidation = true;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
            $now = time();
            // обновляем не чаще, чем раз в 2 минуты
            if (empty($user->online_at) || ($now - (int)$user->online_at) >= 120) {
                // Быстрый апдейт без валидаций
                AdminUser::updateAll(['online_at' => $now], ['id' => $user->id]);
            }
        }

        return true;
    }
}