<?php
namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\models\File;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

class FileField extends Field
{
    /** @var boolean Мультизагрузка */
    public $multiple;

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array Массив с данным для GridView
     */
    public function renderList(string $column): array
    {
        $maxVisible = 3;
        return [
            'attribute' => $column,
            'format' => 'raw',
            'filter' => ['' => '---', '1' => 'Есть', '0' => 'Нет'],
            'value' => function ($data) use ($column, $maxVisible) {
                /** @var File[] $files */
                $files = FieldsHelper::getFiles($data, $this->name);
                if (empty($files)) {
                    return $this->renderEmptyPlaceholder();
                }

                $images = [];
                $nonImages = [];
                $lightboxGroup = $this->name . '-' . $data->id;

                foreach ($files as $file) {
                    if ($file->isImage()) {
                        $images[] = $file;
                    } else {
                        $nonImages[] = $file;
                    }
                }

                $html = '';

                if (!empty($images)) {
                    $html .= $this->renderImageStack(
                        $images,
                        $lightboxGroup,
                        $maxVisible
                    );
                }

                foreach ($nonImages as $file) {
                    $html .= ' ' . Html::a(
                        Html::encode($file->filename ?: $file->path),
                        $file->getUrl() ?: $file->path,
                        [
                            'target' => '_blank',
                            'rel' => 'noopener',
                            'class' => 'file-link',
                        ]
                    );
                }

                return $html;
            }
        ];
    }

    /**
     * Рендерит image-stack с превью и more-indicator.
     *
     * @param File[] $images
     * @param string $lightboxGroup
     * @param int $maxVisible
     * @return string
     */
    private function renderImageStack(
        array $images,
        string $lightboxGroup,
        int $maxVisible
    ): string {
        $total = count($images);
        $visible = array_slice($images, 0, $maxVisible);
        $hidden = array_slice($images, $maxVisible);
        $zIndex = $maxVisible + 1;
        $items = [];

        foreach ($visible as $file) {
            $zIndex--;
            $url = $file->getUrl() ?: $file->path;
            $items[] = Html::a(
                Html::img($url, [
                    'alt' => $file->alt_attribute,
                    'title' => $file->alt_attribute,
                    'style' => 'z-index:' . $zIndex,
                ]),
                $url,
                [
                    'data' => [
                        'lightbox' => $lightboxGroup,
                        'title' => $file->alt_attribute,
                    ],
                    'class' => 'stack-image',
                ]
            );
        }

        // Скрытые изображения — не видны, но участвуют в lightbox
        foreach ($hidden as $file) {
            $url = $file->getUrl() ?: $file->path;
            $items[] = Html::a('', $url, [
                'data' => [
                    'lightbox' => $lightboxGroup,
                    'title' => $file->alt_attribute,
                ],
                'class' => 'stack-image-hidden',
                'style' => 'display:none',
            ]);
        }

        // Индикатор «ещё N»
        if ($total > $maxVisible) {
            $rest = $total - $maxVisible;
            $items[] = Html::tag('div', '+' . $rest, [
                'class' => 'more-indicator js-stack-more',
                'title' => 'Ещё ' . $rest,
                'data-lightbox-group' => $lightboxGroup,
            ]);
        }

        return Html::tag('div', implode('', $items), [
            'class' => 'image-stack',
        ]);
    }

    /**
     * Заглушка для пустого поля.
     * @return string
     */
    private function renderEmptyPlaceholder(): string
    {
        return '<div class="image-placeholder">'
            . '<i class="icon-image"></i>'
            . '</div>';
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
                "file" => $file->getUrl() ?: $file->path,
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
            $storage = \Yii::createObject(\Mitisk\Yii2Admin\components\FileStorage::class);
            $storageType = $storage->getStorageType();

            $i = 0;
            foreach ($files as $file) {
                // Debug logging
                try {
                    $logPath = \Yii::getAlias('@webroot/file_field_debug.log');
                    $msg = date('Y-m-d H:i:s') . " - FileField save. Detected Storage Type: '$storageType'. File: '{$file->name}'. Temp: '{$file->tempName}'\n";
                    @file_put_contents($logPath, $msg, FILE_APPEND);
                } catch (\Exception $e) {}

                $i++;
                // Generate unique filename
                $filename = uniqid() . '.' . $file->extension;

                // Если S3, добавляем папку с именем модели
                if ($storageType === 's3') {
                    $modelName = strtolower(StringHelper::basename(get_class($this->model->getModel())));
                    $filename = $modelName . '/' . $filename;
                }
                
                // Use FileStorage to save
                // For local storage, we want to maintain the 'uploads/' directory convention if not handled by FileStorage settings?
                // FileStorage::saveLocal uses 'local_upload_dir' setting, defaulting to 'uploads/'. 
                // That seems compatible with current '/uploads/'.
                
                $savedPath = $storage->save($file->tempName, $filename);

                if ($savedPath !== false) {
                     $fileModel = new File();
                     $fileModel->class_name = get_class($this->model->getModel());
                     $fileModel->item_id = $this->model->getModel()->id;
                     $fileModel->field_name = $this->name;
                     $fileModel->filename = $file->name;
                     $fileModel->file_size = $file->size;
                     $fileModel->mime_type = $file->type;
                     $fileModel->storage_type = $storageType;
                     $fileModel->alt_attribute = ArrayHelper::getValue($tempFiles, $i . '.alt');

                     // Handle path differences for legacy local support vs new storage
                     if ($storageType === 'local') {
                         // Previous logic stored '/web/uploads/filename'
                         // FileStorage::saveLocal returns 'uploads/filename' (relative to webroot usually)
                         // We might need to prepend '/web' if the application relies on it?
                         // Looking at line 190: $fileModel->path = '/web' . $filePath; where $filePath was '/uploads/...'
                         // So it was storing '/web/uploads/...'
                         // Let's replicate this prefix if it's really needed, OR rely on File::getUrl/isImage handling it.
                         // But to be safe and consistent with previous "FileField" behavior:
                         $fileModel->path = '/web/' . ltrim($savedPath, '/'); 
                     } else {
                         // For S3/FTP, store the key/path as is
                         $fileModel->path = $savedPath;
                     }

                     if ($fileModel->save()) {
                         // Если у модели есть поле (например file_id), записываем туда ID файла
                         // Делаем это только если это не мультизагрузка, или берем последний
                         // Обычно привязка ID идет для одиночных полей.
                         $relatedModel = $this->model->getModel();
                         if ($relatedModel->hasAttribute($this->name)) {
                             $relatedModel->setAttribute($this->name, $fileModel->id);
                             $relatedModel->save(false, [$this->name]);
                         }
                     } else {
                         $this->model->getModel()->addError($this->name, 'Ошибка при сохранении записи файла в БД');
                     }
                } else {
                    $this->model->getModel()->addError($this->name, 'Невозможно сохранить файл "' . $file->name . '"');
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
