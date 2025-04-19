<?php
namespace Mitisk\Yii2Admin\components;

use yii\base\BaseObject;


class AdminDashboardHelper extends BaseObject
{
    /**
     * Возвращает название роли текущего пользователя
     * @return string
     */
    public static function getCurrentUserRoleName()
    {
        $roles = \Yii::$app->authManager->getRolesByUser(\Yii::$app->user->getId());
        $array = [];

        if ($roles) {
            foreach ($roles as $role) {
                $array[] = $role->description;
            }
        }

        if (empty($array)) {
            $array = ['Гость'];
        }

        return implode('<br>', $array);
    }
}
