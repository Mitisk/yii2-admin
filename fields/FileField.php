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

                // Fallback: значение хранится в атрибуте модели
                if (empty($files)) {
                    $url = $this->_resolveAttributeUrl($data);
                    if ($url !== null) {
                        return $this->_renderInlineImage($url, $data);
                    }
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
        $files = FieldsHelper::getFiles(
            $this->model->getModel(),
            $this->name
        );
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
                    'field_name' => $this->name,
                ],
            ];
        }

        // Fallback: файл в атрибуте модели + file_path
        if (empty($preloadedFiles)) {
            $url = $this->_resolveAttributeUrl(
                $this->model->getModel()
            );
            if ($url !== null) {
                $model = $this->model->getModel();
                $raw = $model->getAttribute($this->name);
                $preloadedFiles[] = [
                    "type" => \yii\helpers\FileHelper::getMimeTypeByExtension(
                        $raw
                    ) ?? 'application/octet-stream',
                    "size" => 0,
                    "file" => $url,
                    "name" => basename($raw),
                    "data" => [
                        'alt' => '',
                        'file_id' => 0,
                        'field_name' => $this->name,
                    ],
                ];
            }
        }

        $preloadedFiles = json_encode($preloadedFiles);

        return $this->render('file', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'files' => $preloadedFiles,
        ]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $files = FieldsHelper::getFiles(
            $this->model->getModel(),
            $this->name
        );
        if ($files) {
            return $this->render('_file_view', [
                'files' => $files,
            ]);
        }

        // Fallback: файл в атрибуте модели + file_path
        $url = $this->_resolveAttributeUrl(
            $this->model->getModel()
        );
        if ($url !== null) {
            return Html::img($url, [
                'style' => 'max-width:320px;max-height:240px;',
            ]);
        }

        return '';
    }

    /**
     * Строит URL из значения атрибута модели + file_path компонента.
     * Возвращает null, если значение пустое или file_path не задан.
     *
     * @param \yii\db\BaseActiveRecord $model
     *
     * @return string|null
     */
    private function _resolveAttributeUrl($model): ?string
    {
        if (!$model->hasAttribute($this->name)) {
            return null;
        }

        $raw = $model->getAttribute($this->name);
        if ($raw === null || $raw === '' || is_numeric($raw)) {
            return null;
        }

        $filePath = $this->_getComponentFilePath();

        // Если значение уже содержит путь (начинается с /)
        // используем как есть
        if (str_starts_with($raw, '/')) {
            return $raw;
        }

        // Нужен file_path чтобы построить URL
        if ($filePath === null) {
            return null;
        }

        return rtrim($filePath, '/') . '/' . ltrim($raw, '/');
    }

    /**
     * Рендерит inline-картинку для GridView (fallback).
     *
     * @param string                   $url  URL файла
     * @param \yii\db\BaseActiveRecord $data Модель записи
     *
     * @return string
     */
    private function _renderInlineImage(string $url, $data): string
    {
        $ext = strtolower(
            pathinfo($url, PATHINFO_EXTENSION)
        );
        $imageExts = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
        ];

        if (in_array($ext, $imageExts, true)) {
            $group = $this->name . '-' . $data->id;
            return Html::tag(
                'div',
                Html::a(
                    Html::img($url, [
                        'alt' => basename($url),
                        'style' => 'z-index:1',
                    ]),
                    $url,
                    [
                        'data' => [
                            'lightbox' => $group,
                            'title' => basename($url),
                        ],
                        'class' => 'stack-image',
                    ]
                ),
                ['class' => 'image-stack']
            );
        }

        return Html::a(
            Html::encode(basename($url)),
            $url,
            ['target' => '_blank', 'rel' => 'noopener']
        );
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
        if ($files) {
            $componentFilePath = $this->_getComponentFilePath();

            $storage = \Yii::createObject(
                \Mitisk\Yii2Admin\components\FileStorage::class
            );
            $storageType = $componentFilePath
                ? 'local'
                : $storage->getStorageType();

            $i = 0;
            foreach ($files as $file) {
                $i++;
                $filename = uniqid() . '.' . $file->extension;

                // Серверный путь компонента — сохраняем локально
                if ($componentFilePath) {
                    $savedPath = $this->_saveToComponentPath(
                        $file->tempName,
                        $filename,
                        $componentFilePath
                    );
                } else {
                    // Если S3, добавляем папку с именем модели
                    if ($storageType === 's3') {
                        $modelName = strtolower(
                            StringHelper::basename(
                                get_class($this->model->getModel())
                            )
                        );
                        $filename = $modelName . '/' . $filename;
                    }
                    $savedPath = $storage->save(
                        $file->tempName,
                        $filename
                    );
                }

                if ($savedPath !== false) {
                    $fileModel = new File();
                    $fileModel->class_name = get_class(
                        $this->model->getModel()
                    );
                    $fileModel->item_id = $this->model
                        ->getModel()->id;
                    $fileModel->field_name = $this->name;
                    $fileModel->filename = $file->name;
                    $fileModel->file_size = $file->size;
                    $fileModel->mime_type = $file->type;
                    $fileModel->storage_type = $storageType;
                    $fileModel->alt_attribute = ArrayHelper::getValue(
                        $tempFiles,
                        $i . '.alt'
                    );

                    if ($componentFilePath) {
                        // Путь компонента уже содержит /web/
                        $fileModel->path = '/'
                            . ltrim($savedPath, '/');
                    } elseif ($storageType === 'local') {
                        $fileModel->path = '/web/'
                            . ltrim($savedPath, '/');
                    } else {
                        $fileModel->path = $savedPath;
                    }

                    if ($fileModel->save()) {
                        $relatedModel = $this->model->getModel();
                        if ($relatedModel->hasAttribute($this->name)) {
                            $relatedModel->setAttribute(
                                $this->name,
                                $fileModel->id
                            );
                            $relatedModel->save(
                                false,
                                [$this->name]
                            );
                        }
                    } else {
                        $this->model->getModel()->addError(
                            $this->name,
                            'Ошибка при сохранении записи файла в БД'
                        );
                    }
                } else {
                    $this->model->getModel()->addError(
                        $this->name,
                        'Невозможно сохранить файл "'
                            . $file->name . '"'
                    );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Возвращает серверный путь для файлов из настроек компонента.
     *
     * @return string|null Путь или null, если не задан
     */
    private function _getComponentFilePath(): ?string
    {
        $filePath = $this->model->component->file_path ?? null;
        if ($filePath === null || $filePath === '') {
            return null;
        }
        return rtrim($filePath, '/') . '/';
    }

    /**
     * Сохраняет файл в серверную директорию компонента.
     *
     * @param string $sourcePath Путь к временному файлу
     * @param string $filename   Имя файла для сохранения
     * @param string $filePath   Путь из настроек компонента
     *
     * @return string|false Относительный путь или false
     */
    private function _saveToComponentPath(
        string $sourcePath,
        string $filename,
        string $filePath
    ): string|false {
        // Приводим file_path к абсолютному пути на диске
        // Например: /web/items/ → @app/web/items/
        $absDir = \Yii::getAlias('@app')
            . '/' . ltrim($filePath, '/');

        if (!is_dir($absDir)) {
            \yii\helpers\FileHelper::createDirectory($absDir);
        }

        $target = $absDir . $filename;
        if (rename($sourcePath, $target)) {
            // Возвращаем путь без ведущего /web/
            // (prefix /web/ добавится при записи в File.path)
            return ltrim($filePath, '/') . $filename;
        }

        return false;
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
