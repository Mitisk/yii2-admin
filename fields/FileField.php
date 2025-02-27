<?php
namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\models\File;
use yii\web\UploadedFile;

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
        /** @var File[] $files */
        $files = FieldsHelper::getFiles($this->model->getModel(), $this->name);
        $preloadedFiles = [];

        foreach($files as $file) {
            $preloadedFiles[] = [
                "type" => $file->mime_type,
                "size" => $file->file_size,
                "file" => $file->path,
                "name" => $file->filename,
                "data" => [
                    'alt' => $file->alt_attribute,
                    'file_id' => $file->id,
                    ],
            ];
        }

        $preloadedFiles = json_encode($preloadedFiles);

        return $this->render('file', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'files' => $preloadedFiles
        ]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $files = FieldsHelper::getFiles($this->model->getModel(), $this->name);
        if ($files) {
            return $this->render('_file_view', [
                'files' => $files,
            ]);
        }
        return '';
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function save() : bool
    {
        $files = UploadedFile::getInstances($this->model->getModel(), $this->name);

        if($files) {
            foreach ($files as $file) {
                $filePath = '/uploads/' . uniqid() . '.' . $file->extension;
                $fileModel = new File();
                $fileModel->class_name = get_class($this->model->getModel());
                $fileModel->item_id = $this->model->getModel()->id;
                $fileModel->field_name = $this->name;
                $fileModel->filename = $file->name;
                $fileModel->file_size = $file->size;
                $fileModel->mime_type = $file->type;
                $fileModel->path = '/web' . $filePath;

                $uploadsDir = \Yii::getAlias('@webroot');

                if ($file->saveAs($uploadsDir . $filePath)) {
                    $fileModel->save();
                } else {
                    $this->model->getModel()->addError($this->name, 'Невозможно сохранить файл "' . $uploadsDir . $fileModel->path . '"');
                    return false;
                }
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
        if($files = FieldsHelper::getFiles($this->model->getModel(), $this->name)) {
            $i = 0;

            /** @var File[] $files */
            foreach ($files as $file) {
                if ($file->delete()) {
                    $i++;
                }
            }

            if ($i == count($files)) {
                return true;
            } else {
                $this->model->getModel()->addError($this->name, 'Не удалось удалить некоторые файлы. Удалите их вручную.');
                return false;
            }
        }
        return true;
    }
}
