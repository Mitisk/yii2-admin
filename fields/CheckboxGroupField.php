<?php
namespace Mitisk\Yii2Admin\fields;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class CheckboxGroupField extends Field
{
    /** @var boolean Toggle */
    public $toggle;

    /** @var boolean Inline */
    public $inline;

    /** @var boolean Other */
    public $other;

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

        if (!$values || $values && count($values) == 1) {
            return [
                'attribute' => $column,
                'format' => 'html',
                'value' => function ($data) use ($column) {
                    return $data->{$column}
                        ? '<div class="block-available">да</div>'
                        : '<div class="block-not-available">нет</div>';
                }
            ];
        }

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
        $values = FieldsHelper::getValues($this);

        if (!$values || $values && count($values) == 1) {
            return $this->render('checkbox', [
                'field' => $this,
                'model' => $this->model,
                'fieldId' => $this->fieldId
            ]);
        }

        $selected = $this->getSelected();

        return $this->render('checkbox-group', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'selected' => $selected,
            'values' => $values
        ]);
    }

    /**
     * Получаем выбранные значения
     * @return array
     */
    private function getSelected() : array
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
        return $selected;
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $values = FieldsHelper::getValues($this);

        if (!$values || $values && count($values) == 1) {
            if (Html::getAttributeValue($this->model->getModel(), $this->name)) {
                return '<div class="block-available">да</div>';
            }
            return '<div class="block-not-available">нет</div>';
        }

        if($selected = $this->getSelected()) {
            $result = array_map(function ($key) use ($values) {
                return $values[$key] ?? null; // Если ключ не найден, возвращаем null
            }, $selected);

            return implode('<br>', $result);
        }

        return ArrayHelper::getValue($values, Html::getAttributeValue($this->model->getModel(), $this->name), '');
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
     * Получаем атрибуты связи
     * @param $relation
     * @return array
     */
    private function getRelationAttribute($relation) : array
    {
        $model = new $relation->modelClass;
        // Получаем список ключей массива
        $keys = array_keys($model->attributes);
        // Фильтруем ключи, оставляя только те, что заканчиваются на "_id"
        $filteredKeys = preg_grep('/_id$/', $keys);

        ArrayHelper::removeValue($filteredKeys, array_key_first($relation->link));

        return array_values($filteredKeys);
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

    /**
     * @inheritdoc
     * @return bool
     */
    public function delete() : bool
    {
        if ($this->publicSaveMethod) {
            if (method_exists($this->model->getModel(), $this->publicSaveMethod)) {
                // Получаем объект связи
                $relation = $this->model->getModel()->getRelation($this->getRelationName());

                // Используем связь
                if ($relation instanceof \yii\db\ActiveQuery) {
                    // Удаляем все существующие связи
                    $this->model->getModel()->unlinkAll($this->getRelationName(), true);
                }
            }
        }
        return true;
    }
}
