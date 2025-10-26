<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\AdminWidget;
use yii\base\Widget;

final class IndexTopWidget extends Widget
{
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $usersWidget = AdminWidget::find()
            ->select(['class', 'published'])
            ->where(['user_id' => \Yii::$app->user->id])
            ->orderBy(['ordering' => SORT_ASC])
            ->asArray()
            ->all();

        $techniciansWidget = AdminWidget::find()
            ->select('class')
            ->where(['user_id' => null, 'published' => 1])
            ->orderBy(['ordering' => SORT_ASC])
            ->asArray()
            ->column();

        //Множество пользовательских классов и фильтрация скрытых
        $userClassesAll = array_column($usersWidget, 'class');
        $userHiddenSet = [];
        $userVisibleOrdered = [];
        foreach ($usersWidget as $w) {
            if ((int)$w['published'] === 1) {
                $userVisibleOrdered[] = $w['class'];
            } else {
                $userHiddenSet[$w['class']] = true;
            }
        }

        //Из техничных исключить:
        //    - те, что уже присутствуют у пользователя (любой published),
        //    - те, что помечены как скрытые (published=0)
        $userClassSet = array_fill_keys($userClassesAll, true);
        $result = [];
        foreach ($techniciansWidget as $tClass) {
            if (!isset($userClassSet[$tClass]) && !isset($userHiddenSet[$tClass])) {
                $result[] = $tClass;
            }
        }

        //Добавить в конец видимые пользовательские (уже отсортированы по ordering)
        foreach ($userVisibleOrdered as $uClass) {
            $result[] = $uClass;
        }

        $count = $result ? count($result) : 0;

        return $this->render('index/top', [
            'availableWidgets' => $result,
            'count' => $count,
        ]);
    }
}
