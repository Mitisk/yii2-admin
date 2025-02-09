<?php
namespace Mitisk\Yii2Admin\fields;

class FileField extends Field
{
    /** @var boolean Мультизагрузка */
    public $multiple;

    /** @var boolean Только для чтения */
    public $readonly;

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    public function renderList(string $column): array
    {
        return [
            'attribute' => $column
        ];
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('file', ['field' => $this, 'model' => $this->model, 'fieldId' => $this->fieldId]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        return '<div class="upload-image mb-16">
                                                <div class="item">
                                                    <img src="/web/images/upload/upload-1.png" alt="">
                                                </div>
                                                <div class="item">
                                                    <img src="/web/images/upload/upload-1.png" alt="">
                                                </div>
                                            </div>';
    }
}
