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
        $array = self::getRolesById(\Yii::$app->user->getId());
        // Удаляем пустые элементы (null, '', false)
        $array = array_filter($array);
        return implode('<br>', $array);
    }

    /**
     * Возвращает название ролей пользователя по идентификатору
     * @param int $id - идентификатор пользователя
     * @param bool $all - показывать все роли или только одну
     * @return array|string
     */
    public static function getRolesById(int $id, bool $all = true)
    {
        $roles = \Yii::$app->authManager->getRolesByUser($id);
        $array = [];

        if ($roles) {
            foreach ($roles as $role) {
                $array[] = $role->description;
            }
        }

        if (empty($array)) {
            $array = ['Гость'];
        }

        if (!$all) {
            return array_shift($array);
        }

        return $array;
    }
}
