<?php

namespace Mitisk\Yii2Admin\fields;

/**
 * Поле slug: текстовый ввод с автогенерацией из соседнего поля
 * (name/title/label) — транслитерация кириллицы, нижний регистр,
 * пробелы/спецсимволы → дефис.
 *
 * Автозаполнение работает, пока slug пуст и пользователь не правил его
 * вручную. Уникальность обеспечивает правило ['unique'] целевой модели
 * (и/или SluggableBehavior).
 */
class SlugField extends TextField
{
    /** @var string */
    public $inputType = 'text';

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('slug', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'formName' => $this->model->getModel()->formName(),
        ]);
    }
}
