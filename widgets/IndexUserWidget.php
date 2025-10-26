<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\AdminUser;
use yii\base\Widget;

final class IndexUserWidget extends Widget
{
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        if (!\Yii::$app->user->can('viewUsers')) {
            return '';
        }

        $userCount = AdminUser::find()->count();
        $userCountThisWeek = AdminUser::find()->andWhere(['>=', 'created_at', (time()-7*24*60*60)])->count();

        return $this->render('index/user', [
            'userCount' => $userCount,
            'userCountThisWeek' => $userCountThisWeek,
        ]);
    }
}
