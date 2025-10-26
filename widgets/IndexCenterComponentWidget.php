<?php

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\models\AdminModel as AdminModelRecord;
use Mitisk\Yii2Admin\models\AdminWidgetComponent;
use Mitisk\Yii2Admin\core\models\AdminModel as AdminModelWrapper;
use yii\base\Widget;
use yii\helpers\ArrayHelper;

/**
 * Виджет центрального компонента на главной админ-странице.
 *
 * Выбирает компонент пользователя (AdminWidgetComponent) по user_id,
 * находит связанный AdminModel (по alias), создает обертку AdminModelWrapper
 * над реальным модельным классом, выполняет поиск c учетом фильтров из GET,
 * ограничивает пагинацию до 5 элементов и выводит GridView со столбцами,
 * предоставленными AdminModelWrapper::getGridColumns().
 *
 * @package Mitisk\Yii2Admin\widgets
 */
final class IndexCenterComponentWidget extends Widget
{
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        // По умолчанию — пустой HTML и отсутствующая модель-обертка.
        $componentHtml = '';
        $adminModelWrapper = null;

        // Ищем пользовательский компонент по текущему пользователю.
        /** @var AdminWidgetComponent|null $userComponent */
        $userComponent = AdminWidgetComponent::findOne(['user_id' => \Yii::$app->user->id]);

        if ($userComponent === null) {
            // Нет привязанного компонента — рендерим пустой блок.
            return $this->render('index/center-component', [
                'userComponent' => $componentHtml,
                'userComponentModel' => $adminModelWrapper,
            ]);
        }

        // Находим запись AdminModel по alias из компонента.
        /** @var AdminModelRecord|null $adminModelRecord */
        $adminModelRecord = AdminModelRecord::find()
            ->where(['alias' => $userComponent->component_alias])
            ->one();

        if ($adminModelRecord === null) {
            // Некорректный alias или запись отсутствует.
            return $this->render('index/center-component', [
                'userComponent' => $componentHtml,
                'userComponentModel' => $adminModelWrapper,
            ]);
        }

        // Проверяем наличие класса модели в конфигурации.
        $modelClass = $adminModelRecord->model_class;
        if (empty($modelClass)) {
            // Для компонента не указан модельный класс — нечего выводить.
            return $this->render('index/center-component', [
                'userComponent' => $componentHtml,
                'userComponentModel' => $adminModelWrapper,
            ]);
        }

        // Создаем экземпляр реальной модели через DI-контейнер и оборачиваем.
        $realModel = \Yii::createObject(['class' => $modelClass]);
        $adminModelWrapper = new AdminModelWrapper($realModel);

        // Проверяем право на просмотр списка.
        if (!$adminModelWrapper->canList()) {
            return $this->render('index/center-component', [
                'userComponent' => $componentHtml,
                'userComponentModel' => $adminModelWrapper,
            ]);
        }

        // Достаем фильтры из GET вида ModelFormName.search
        // GridView отображает данные из DataProvider и поддерживает пагинацию/сортировку/фильтрацию.
        $formName = $adminModelWrapper->getModel()->formName();
        $searchParams = ArrayHelper::getValue(\Yii::$app->request->get(), $formName . '.search');

        // Получаем DataProvider через search() и ограничиваем размер страницы.
        $dataProvider = $adminModelWrapper->search($searchParams);
        // Параметр pageSize задает количество элементов на страницу; по умолчанию 20.
        if (isset($dataProvider->pagination)) {
            $dataProvider->pagination->pageSize = 5;
        }

        // Рендерим GridView с колонками, предоставленными моделью-оберткой.
        // Минимальная конфигурация GridView — передать dataProvider; здесь также передаем filterModel и классы.
        $componentHtml = GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $adminModelWrapper->getModel(),
            'tableOptions' => ['class' => 'wg-table table-all-roles'],
            'rowOptions' => ['class' => 'roles-item'],
            'contentOptions' => ['class' => 'body-text'],
            'columns' => $adminModelWrapper->getGridColumns(),
        ]);

        // Возвращаем итоговый рендер секции.
        return $this->render('index/center-component', [
            'userComponent' => $componentHtml,
            'userComponentModel' => $adminModelWrapper,
        ]);
    }
}
