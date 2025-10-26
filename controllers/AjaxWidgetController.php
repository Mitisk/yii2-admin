<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\widgets\IndexCenterComponentWidget;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use Mitisk\Yii2Admin\models\AdminWidget;
use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\AdminWidgetComponent;

class AjaxWidgetController extends Controller
{
    public function actionAdd()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;

        if ($request->isPost) {
            $widgetClass = $request->post('class');

            if (empty($widgetClass)) {
                return ['success' => false, 'message' => 'Класс виджета не указан'];
            }

            // Проверяем существование класса
            if (!class_exists($widgetClass)) {
                return ['success' => false, 'message' => 'Класс виджета не найден'];
            }

            $widget = new AdminWidget();
            $widget->class = $widgetClass;
            $widget->user_id = Yii::$app->user->id;
            $widget->published = 1;
            $widget->alias = (new \ReflectionClass($widgetClass))->getShortName();

            if ($widget->save()) {
                try {
                    // Генерируем HTML код виджета
                    $widgetHtml = $this->renderWidgetHtml($widget);

                    return [
                        'success' => true,
                        'html' => $widgetHtml,
                        'alias' => $widget->alias,
                        'message' => 'Виджет добавлен успешно'
                    ];
                } catch (\Exception $e) {
                    // Удаляем запись если виджет не рендерится
                    $widget->delete();
                    return ['success' => false, 'message' => 'Ошибка рендеринга виджета: ' . $e->getMessage()];
                }
            } else {
                return ['success' => false, 'message' => 'Ошибка сохранения: ' . implode(', ', $widget->getFirstErrors())];
            }
        }

        return ['success' => false, 'message' => 'Неверный запрос'];
    }

    public function actionUpdateOrder()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $request = \Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'message' => 'Неверный запрос'];
        }

        // SortableJS отправляет JSON-массив алиасов
        $aliasesJson = $request->post('aliases');
        $aliases = json_decode($aliasesJson, true);
        $userId = \Yii::$app->user->id;

        if (empty($aliases) || !is_array($aliases)) {
            return ['success' => false, 'message' => 'Список виджетов пуст'];
        }

        // Удалим дубликаты, сохраняя порядок
        $seen = [];
        $orderedAliases = [];
        foreach ($aliases as $al) {
            if (!isset($seen[$al])) {
                $seen[$al] = true;
                $orderedAliases[] = $al;
            }
        }

        $transaction = \Yii::$app->db->beginTransaction();

        try {
            //Собираем alias, которые уже есть у пользователя
            $userHas = AdminWidget::find()
                ->select(['alias'])
                ->where(['user_id' => $userId])
                ->andWhere(['alias' => $orderedAliases])
                ->indexBy('alias')
                ->asArray()
                ->all();

            //Определяем недостающие alias
            $missing = [];
            foreach ($orderedAliases as $al) {
                if (!isset($userHas[$al])) {
                    $missing[] = $al;
                }
            }

            if (!empty($missing)) {
                // Берем «шаблонные» записи без user_id (общие) по недостающим alias
                $templates = AdminWidget::find()
                    ->where(['user_id' => null])
                    ->andWhere(['alias' => $missing])
                    ->all();

                // Индексируем шаблоны по alias для быстрого доступа
                $tplByAlias = [];
                foreach ($templates as $tpl) {
                    $tplByAlias[$tpl->alias] = $tpl;
                }

                // Копируем недостающие для пользователя
                foreach ($missing as $al) {
                    if (!isset($tplByAlias[$al])) {
                        // Если нет шаблона — можно либо пропустить, либо бросить исключение
                        // Бросим исключение, чтобы фронт понял, что порядок сохранить нельзя
                        throw new \RuntimeException("Шаблон виджета с alias '{$al}' не найден");
                    }

                    $tpl = $tplByAlias[$al];
                    $copy = new AdminWidget();
                    // Скопируем безопасно нужные атрибуты
                    $copy->alias = $tpl->alias;
                    $copy->class = $tpl->class;
                    $copy->published = (int)$tpl->published ?: 1;
                    // Назначим ordering временно в конец; далее перезапишем циклом ниже
                    $copy->ordering = 999999;
                    $copy->user_id = $userId;

                    if (!$copy->save()) {
                        $errors = implode(', ', $copy->getFirstErrors());
                        throw new \RuntimeException("Не удалось скопировать виджет '{$al}': {$errors}");
                    }
                }
            }

            // Обновляем ordering в соответствии с поступившим массивом
            foreach ($orderedAliases as $index => $alias) {
                AdminWidget::updateAll(
                    ['ordering' => $index + 1],
                    ['alias' => $alias, 'user_id' => $userId]
                );
            }

            $transaction->commit();
            return ['success' => true, 'message' => 'Порядок обновлен'];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            return ['success' => false, 'message' => 'Ошибка обновления порядка: ' . $e->getMessage()];
        }
    }

    public function actionHide()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;

        if ($request->isPost) {
            $alias = $request->post('alias');
            $userId = Yii::$app->user->id;

            if (empty($alias)) {
                return ['success' => false, 'message' => 'Alias не указан'];
            }

            $widget = AdminWidget::findOne(['alias' => $alias, 'user_id' => $userId]);

            if (!$widget) {
                $find = AdminWidget::find()->where(['alias' => $alias, 'user_id' => null, 'published' => 1])->one();

                if ($find) {
                    $widget = new AdminWidget();
                    $widget->setAttributes($find->getAttributes());
                    $widget->user_id = $userId;
                }
            }

            if (!$widget) {
                return ['success' => false, 'message' => 'Виджет не найден'];
            }

            $widget->published = 0;

            if ($widget->save()) {
                return ['success' => true, 'message' => 'Виджет скрыт'];
            } else {
                return ['success' => false, 'message' => 'Ошибка скрытия виджета'];
            }
        }

        return ['success' => false, 'message' => 'Неверный запрос'];
    }

    private function renderWidgetHtml($widget)
    {
        $widgetClass = $widget->class;

        // Создаем HTML блок виджета
        $html = '';

        // Рендерим содержимое виджета
        try {
            $html .= $widgetClass::widget();
        } catch (\Exception $e) {
            $html .= '<div class="alert alert-danger">Ошибка загрузки виджета: ' . $e->getMessage() . '</div>';
        }

        return $html;
    }

    // Рендер HTML попапа со списком компонентов
    public function actionComponentPopup()
    {
        $components = AdminModel::find()
            ->select(['name', 'alias'])
            ->andWhere(['view' => 1])
            ->andWhere(['not', ['alias' => null]])
            ->andWhere(['not', ['name' => null]])
            ->asArray()
            ->all();

        return $this->renderPartial('_component_popup', [
            'components' => $components,
        ]);
    }

    // Принимает alias, пишет запись и отдаёт компонент
    public function actionSaveComponent()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $alias = Yii::$app->request->post('alias');
        if (!$alias) {
            return ['success' => false, 'message' => 'Не передан alias компонента'];
        }

        AdminWidgetComponent::deleteAll(['user_id' => Yii::$app->user->id]);

        $model = new AdminWidgetComponent();
        $model->user_id = Yii::$app->user->id;
        $model->component_alias = $alias;
        $model->created_at = time();
        $model->updated_at = time();

        if (!$model->save()) {
            return ['success' => false, 'message' => 'Ошибка сохранения', 'errors' => $model->errors];
        }

        return ['success' => true, 'html' => IndexCenterComponentWidget::widget()];
    }
}
