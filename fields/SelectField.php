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
        $adminAlias = $this->getRelatedAdminAlias();

        // Множественный выбор (viaTable)
        if ($this->multiple && $this->publicSaveMethod) {
            $relationName = $this->getRelationName();

            return [
                'attribute' => $column,
                'format' => 'raw',
                'filter' => ['' => '---'] + $values,
                'value' => function ($data) use (
                    $values,
                    $relationName,
                    $adminAlias
                ) {
                    $related = $data->{$relationName};
                    if (empty($related)) {
                        return '-';
                    }
                    $items = [];
                    foreach ($related as $item) {
                        $items[] = [
                            'id' => $item->id,
                            'label' => $values[$item->id]
                                ?? (string)$item->id,
                        ];
                    }
                    return self::renderBadges(
                        $items,
                        $adminAlias
                    );
                },
            ];
        }

        // Одиночный выбор
        return [
            'attribute' => $column,
            'format' => 'raw',
            'filter' => ['' => '---'] + $values,
            'value' => function ($data) use (
                $values,
                $column,
                $adminAlias
            ) {
                $val = $data->{$column};
                if ($val === null || $val === '') {
                    return '-';
                }
                $label = $values[$val] ?? (string)$val;
                return self::renderBadge(
                    $val,
                    $label,
                    $adminAlias
                );
            },
        ];
    }

    /**
     * Палитра: фон + цвет текста.
     */
    private const BADGE_PALETTE = [
        ['#e0f2fe', '#0369a1'], // sky
        ['#fce7f3', '#be185d'], // pink
        ['#d1fae5', '#065f46'], // emerald
        ['#fef3c7', '#92400e'], // amber
        ['#ede9fe', '#5b21b6'], // violet
        ['#fee2e2', '#991b1b'], // red
        ['#ccfbf1', '#115e59'], // teal
        ['#fef9c3', '#854d0e'], // yellow
        ['#e0e7ff', '#3730a3'], // indigo
        ['#f3e8ff', '#7e22ce'], // purple
    ];

    /**
     * Стабильный цвет по ID значения.
     *
     * @param int|string $id Идентификатор
     *
     * @return array [bg, color]
     */
    protected static function colorById($id): array
    {
        $palette = self::BADGE_PALETTE;
        $index = abs(crc32((string)$id)) % count($palette);
        return $palette[$index];
    }

    /**
     * Рендерит один бейдж.
     *
     * @param int|string $id         Идентификатор
     * @param string     $label      Текст
     * @param string     $adminAlias Алиас компонента или ''
     *
     * @return string
     */
    protected static function renderBadge(
        $id,
        string $label,
        string $adminAlias
    ): string {
        [$bg, $fg] = self::colorById($id);
        $style = "background:{$bg};color:{$fg};"
            . 'padding:1px 8px;border-radius:5px;'
            . 'font-size:10px;font-weight:600;'
            . 'display:inline-block;'
            . 'text-decoration:none;';

        $text = Html::encode($label);

        if ($adminAlias !== '') {
            $url = '/admin/' . $adminAlias
                . '/view/?id=' . $id;
            return Html::a($text, $url, ['style' => $style]);
        }

        return Html::tag('span', $text, ['style' => $style]);
    }

    /**
     * Рендерит набор бейджей.
     *
     * @param array  $items      [[id, label], ...]
     * @param string $adminAlias Алиас компонента или ''
     *
     * @return string
     */
    protected static function renderBadges(
        array $items,
        string $adminAlias
    ): string {
        $badges = [];
        foreach ($items as $item) {
            $badge = self::renderBadge(
                $item['id'],
                $item['label'],
                $adminAlias
            );
            // Добавляем margin для множественных
            $badges[] = str_replace(
                'display:inline-block;',
                'display:inline-block;'
                    . 'margin:2px 3px 2px 0;',
                $badge
            );
        }
        return implode('', $badges);
    }

    /**
     * Ищет алиас админ-компонента для связанной модели.
     *
     * @return string Алиас или пустая строка
     */
    protected function getRelatedAdminAlias(): string
    {
        $relatedClass = $this->getRelatedModelClass();
        if ($relatedClass === '') {
            return '';
        }

        $component = \Mitisk\Yii2Admin\models\AdminModel::find()
            ->select('alias')
            ->where(
                [
                    'model_class' => $relatedClass,
                    'view' => 1,
                ]
            )
            ->one();

        return $component->alias ?? '';
    }

    /**
     * FQCN связанной модели из relation-метода.
     *
     * @return string Класс или пустая строка
     */
    protected function getRelatedModelClass(): string
    {
        $method = $this->publicStaticMethod
            ?: $this->publicSaveMethod;
        if (empty($method)) {
            return '';
        }

        $modelObj = $this->model->getModel();
        if (!method_exists($modelObj, $method)) {
            return '';
        }

        $query = $modelObj->{$method}();
        if (!$query instanceof \yii\db\ActiveQuery) {
            return '';
        }

        return $query->modelClass ?? '';
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('select', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'selected' => $this->getSelected(),
            'values' => FieldsHelper::getValues($this)
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
                $related = $this->model->getModel()->{$this->getRelationName()};
                if ($related) {
                    $selected = ArrayHelper::map(
                        ArrayHelper::toArray($related),
                        'id',
                        'id'
                    );
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
        $adminAlias = $this->getRelatedAdminAlias();
        $value = Html::getAttributeValue(
            $this->model->getModel(),
            $this->name
        );

        if ($selected = $this->getSelected()) {
            $items = [];
            foreach ($selected as $id) {
                $items[] = [
                    'id' => $id,
                    'label' => $values[$id] ?? (string)$id,
                ];
            }
            return self::renderBadges($items, $adminAlias);
        }

        if ($value === null || $value === '') {
            return '-';
        }

        $label = $values[$value] ?? (string)$value;
        return self::renderBadge($value, $label, $adminAlias);
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
     *
     * @param \yii\db\ActiveQuery $relation Объект связи
     *
     * @return array
     */
    protected function getRelationAttribute($relation) : array
    {
        $model = new $relation->modelClass;
        $keys = array_keys($model->attributes);
        $filteredKeys = preg_grep('/_id$/', $keys);

        ArrayHelper::removeValue(
            $filteredKeys,
            array_key_first($relation->link)
        );

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

                    $data = \Yii::$app->request->post(
                        $this->model->getModel()->formName()
                    );

                    if ($data) {
                        // Получаем имя модели из связи
                        $className = $relation->modelClass;

                        // Ищем данные по имени поля или по имени связи
                        $rawData = ArrayHelper::getValue($data, $this->name)
                            ?? ArrayHelper::getValue(
                                $data,
                                $this->getRelationName()
                            );

                        // Приводим данные к массиву
                        $relatedData = is_array($rawData)
                            ? $rawData
                            : [$rawData];

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
