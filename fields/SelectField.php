<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class SelectField extends Field
{
    /** @var boolean Allow Multiple Selections */
    public $multiple;

    /** @var array Values [label, value, selected] */
    public $values;

    /** @var boolean Только для чтения */
    public $readonly;

    /** @var string Публичный статический метод, который возвращает массив значений */
    public $publicStaticMethod;

    /** @var string Публичный статический метод, который сохраняет значения */
    public $publicSaveMethod = '';

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    public function renderList(string $column): array
    {
        $values = FieldsHelper::getValues($this);

        return [
            'attribute' => $column,
            'value' => function ($data) use ($values, $column) {
                return ArrayHelper::getValue($values, $data->{$column});
            }
        ];
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        $selected = [];

        if ($this->publicSaveMethod) {
            // Проверяем, существует ли метод связи
            if (method_exists($this->model->getModel(), $this->publicSaveMethod)) {

                $selected = $this->model->getModel()->{$this->getRelationName()};
                if ($selected) {
                    $selected = ArrayHelper::map(ArrayHelper::toArray($selected), 'id', 'id');
                }

                // Получаем объект связи
                $relation = $this->model->getModel()->getRelation($this->getRelationName());

                if (!$relation->via instanceof \yii\db\ActiveQuery) {

                    $filteredKeys = $this->getRelationAttribute($relation);

                    if (count($filteredKeys) === 1) {
                        $selected = $relation->select($filteredKeys)->asArray()->all();
                        $selected = ArrayHelper::map($selected, $filteredKeys[0], $filteredKeys[0]);
                    }

                }

            }

        }

        return $this->render('select', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'selected' => $selected,
            'values' => FieldsHelper::getValues($this)
        ]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $values = FieldsHelper::getValues($this);
        $value = Html::getAttributeValue($this->model->getModel(), $this->name);
        return ArrayHelper::getValue($values, $value, '-');
    }

    /**
     * Получаем имя связи
     * @return string Имя связи
     */
    private function getRelationName() : string
    {
        // Удаляем префикс 'get'
        $relationName = ltrim($this->publicSaveMethod, 'get');

        // Приводим имя связи к нижнему регистру
        return strtolower($relationName);
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function save() : bool
    {
        if ($this->publicSaveMethod) {

            // Проверяем, существует ли метод связи
            if (method_exists($this->model->getModel(), $this->publicSaveMethod)) {

                // Получаем объект связи
                $relation = $this->model->getModel()->getRelation($this->getRelationName());

                // Используем связь
                if ($relation instanceof \yii\db\ActiveQuery) {
                    // Удаляем все существующие связи
                    $this->model->getModel()->unlinkAll($this->getRelationName(), true);

                    $data = \Yii::$app->request->post($this->model->getModel()->formName());

                    if ($data) {
                        // Получаем имя модели из связи
                        $className = $relation->modelClass;

                        // Приводим данные к массиву, если передано одно значение
                        $relatedData = is_array(ArrayHelper::getValue($data, $this->getRelationName())) ? $data[$this->getRelationName()] : [ArrayHelper::getValue($data, $this->getRelationName())];

                        foreach ($relatedData as $pk) {
                            // Пропускаем пустые значения
                            if (empty($pk)) {
                                continue;
                            }

                            // Строим запрос для поиска модели по первичному ключу
                            $query = $className::find()->limit(1);
                            foreach ($relation->link as $relatedField => $currentField) {
                                $query->andWhere([$relatedField => $pk]);
                            }

                            // Ищем модель. Если не нашли ничего, создаем новую.
                            $relModel = $query->one() ?? new $className();

                            //viaTable
                            if ($relation->via instanceof \yii\db\ActiveQuery) {
                                // Если новая модель — заполняем её данными
                                if ($relModel->isNewRecord) {
                                    $relModel->setAttributes([$relation->link[array_key_first($relation->link)] => $pk]);
                                    $relModel->save(false); // Сохраняем модель без валидации
                                }

                                // Линкуем модель с текущей
                                $this->model->getModel()->link($this->getRelationName(), $relModel);
                            } else {
                                //Находим атрибут для связи
                                $filteredKeys = $this->getRelationAttribute($relation);

                                if (count($filteredKeys) !== 1) {
                                    //Если ключей больше одного, то связь не может быть
                                    $this->model->getModel()->addError($this->getRelationName(), 'Невозможно сохранить. В связи "' . $this->getRelationName() . '" должны быть 2 ключа с именем "_id"');
                                    return false;
                                }

                                $relModel->setAttributes([
                                    array_key_first($relation->link) => $this->model->getModel()->id,
                                    $filteredKeys[0] => $pk
                                ]);

                                $relModel->save(false); // Сохраняем модель без валидации
                            }

                        }
                    }
                } else {
                    $this->model->getModel()->addError($this->getRelationName(), 'Невозможно сохранить. Связь "' . $this->getRelationName() . '" должна наследоваться от "\yii\db\ActiveQuery"');
                    return false;
                }
            } else {
                $this->model->getModel()->addError($this->getRelationName(), 'Невозможно сохранить. Метод "' . $this->publicSaveMethod . '" отсутствует в классе ' . get_class($this->model->getModel()));
                return false;
            }
        }
        return true;
    }

}
