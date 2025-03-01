<?php
namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\models\File;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
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

        foreach ($files as $file) {
            $preloadedFiles[] = [
                "type" => $file->mime_type,
                "size" => $file->file_size,
                "file" => $file->path,
                "name" => $file->filename,
                "data" => [
                    'alt' => $file->alt_attribute,
                    'file_id' => $file->id,
                    'field_name' => $this->name
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
     */
    public function save() : bool
    {
        $files = UploadedFile::getInstances($this->model->getModel(), $this->name);

        $FileUploaderList = \Yii::$app->request->post('FileUploader');

        $tempFiles = ArrayHelper::getValue($FileUploaderList, 'temp.' . $this->name) ?? [];

        $allFiles = ArrayHelper::getValue($FileUploaderList, $this->name) ?
            ArrayHelper::map(ArrayHelper::getValue($FileUploaderList, $this->name), 'id', 'alt') :
            [];

        //Сохраняем изменения в alt атрибутах
        if($allFiles) {
            try {
                // Готовим выражение для CASE WHEN
                $caseExpression = new Expression(
                    'CASE ' . implode(' ', array_map(function ($id, $value) {
                        return "WHEN id = $id THEN :alt_$id";
                    }, array_keys($allFiles), $allFiles)) . ' ELSE alt_attribute END'
                );

                // Подготовка параметров для привязки значений
                $params = [];
                foreach ($allFiles as $id => $value) {
                    $params[":alt_$id"] = $value;
                }

                // Выполняем массовое обновление
                File::updateAll(
                    ['alt_attribute' => $caseExpression],
                    ['id' => array_keys($allFiles)],
                    $params
                );
            } catch (\Exception $e) {}

            // Удаляем записи, которые не находятся в массиве $allFiles
            $toDelete = File::find()->where([
                'and',
                ['class_name' => get_class($this->model->getModel())],
                ['item_id' => $this->model->getModel()->id],
                ['field_name' => $this->name],
                ['not in', 'id', array_keys($allFiles)]
            ])->all();

            foreach ($toDelete as $file) {
                $file->delete();
            }
        } else {
            // Удаляем все записи, которые привязаны к этому полю
            $toDelete = File::find()->where([
                'and',
                ['class_name' => get_class($this->model->getModel())],
                ['item_id' => $this->model->getModel()->id],
                ['field_name' => $this->name]
            ])->all();

            foreach ($toDelete as $file) {
                $file->delete();
            }
        }

        //Добавляем новые файлы
        if($files) {
            $i = 0;
            foreach ($files as $file) {
                $i++;
                $filePath = '/uploads/' . uniqid() . '.' . $file->extension;
                $fileModel = new File();
                $fileModel->class_name = get_class($this->model->getModel());
                $fileModel->item_id = $this->model->getModel()->id;
                $fileModel->field_name = $this->name;
                $fileModel->filename = $file->name;
                $fileModel->file_size = $file->size;
                $fileModel->mime_type = $file->type;
                $fileModel->path = '/web' . $filePath;
                $fileModel->alt_attribute = ArrayHelper::getValue($tempFiles, $i . '.alt');

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
