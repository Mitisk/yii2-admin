<?php

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\fields\FieldsHelper;
use Yii;
use yii\helpers\FileHelper;

/**
 * This is the model class for table "{{%file}}".
 *
 * @property int $id
 * @property string|null $filename
 * @property string|null $class_name
 * @property int|null $item_id
 * @property string|null $field_name
 * @property string|null $uploaded_at
 * @property string|null $alt_attribute
 * @property string $mime_type
 * @property string $path
 * @property string $storage_type
 */
class File extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName() : string
    {
        return '{{%file}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['item_id', 'file_size'], 'integer'],
            [['uploaded_at'], 'safe'],
            [['file_size', 'mime_type', 'path'], 'required'],
            [['filename', 'class_name', 'field_name', 'alt_attribute'], 'string', 'max' => 255],
            [['mime_type'], 'string', 'max' => 100],
            [['path'], 'string', 'max' => 1000],
            [['storage_type'], 'string', 'max' => 20],
            [['storage_type'], 'default', 'value' => 'local'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'id' => 'ID',
            'filename' => 'Имя загруженного файла',
            'class_name' => 'Class Name',
            'item_id' => 'ID связанного элемента',
            'field_name' => 'Поле связанного элемента',
            'uploaded_at' => 'Дата и время загрузки файла',
            'alt_attribute' => 'Alt-атрибут для файла',
            'file_size' => 'Размер файла в байтах',
            'mime_type' => 'MIME-тип файла',
            'path' => 'Путь к файлу в системе хранения',
            'storage_type' => 'Тип хранилища',
        ];
    }

    /**
     * Возвращает публичный URL файла.
     * Если у компонента задан file_path, используется он как базовый путь.
     *
     * @return string|null
     */
    public function getUrl()
    {
        $filePath = $this->getComponentFilePath();
        if ($filePath !== null) {
            $path = $this->path;
            // Если path уже содержит file_path — не дублируем
            if (!str_starts_with('/' . ltrim($path, '/'), $filePath)) {
                $path = rtrim($filePath, '/') . '/' . ltrim($path, '/');
            }
            $storage = \Yii::createObject(
                \Mitisk\Yii2Admin\components\FileStorage::class
            );
            return $storage->getUrl($path, 'local');
        }

        $storage = \Yii::createObject(
            \Mitisk\Yii2Admin\components\FileStorage::class
        );
        return $storage->getUrl($this->path, $this->storage_type);
    }

    /**
     * Проверяет, является ли файл изображением
     * @return bool
     */
    public function isImage() : bool
    {
        $publicPath = $this->getUrl();

        // Fallback for local files if no URL or relative path
        if (!$publicPath) {
             $publicPath = $this->path;
        }

        // Попытка получить локальный путь (только для local)
        $localPath = null;
        if ($this->storage_type === 'local') {
             $localPath = $this->localPath ?? null;
             if (!$localPath) {
                $resolvedPath = $this->getResolvedPath();
                // Путь вида /web/items/file.png → @app/web/items/file.png
                $appCandidate = \Yii::getAlias('@app')
                    . '/' . ltrim($resolvedPath, '/');
                // Путь вида uploads/file.png → @webroot/uploads/file.png
                $webCandidate = \Yii::getAlias('@webroot')
                    . '/' . ltrim($resolvedPath, '/');
                if (is_file($appCandidate)) {
                    $localPath = $appCandidate;
                } elseif (is_file($webCandidate)) {
                    $localPath = $webCandidate;
                }
             }
        }

        return FieldsHelper::isImageFile($localPath, $publicPath);
    }

    /**
     * Возвращает path с учётом file_path компонента.
     *
     * @return string
     */
    public function getResolvedPath(): string
    {
        $filePath = $this->getComponentFilePath();
        if ($filePath !== null) {
            $path = $this->path;
            if (!str_starts_with(
                '/' . ltrim($path, '/'),
                $filePath
            )) {
                return rtrim($filePath, '/')
                    . '/' . ltrim($path, '/');
            }
        }
        return $this->path;
    }

    /**
     * Возвращает file_path из настроек компонента (AdminModel).
     * Результат кешируется на уровне класса.
     *
     * @return string|null
     */
    protected function getComponentFilePath(): ?string
    {
        static $cache = [];

        $className = $this->class_name;
        if (empty($className)) {
            return null;
        }

        if (!array_key_exists($className, $cache)) {
            $component = AdminModel::find()
                ->select('file_path')
                ->where([
                    'model_class' => $className,
                    'view' => 1,
                ])
                ->one();
            $val = $component->file_path ?? null;
            $cache[$className] = ($val !== null && $val !== '')
                ? $val
                : null;
        }

        return $cache[$className];
    }

    public function generateFileUploaderData($inputName = null) : array
    {
        $url = $this->getUrl();

        return [
            'name' => $this->filename,
            'file' => $url ?: $this->path,
            'type' => $this->mime_type,
            'size' => (int)$this->file_size,
            'data' => [
                'file_id'    => (int)$this->id,
                'field_name' => $inputName,
                'alt'        => $this->alt_attribute,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete() : bool
    {
        $storage = \Yii::createObject(
            \Mitisk\Yii2Admin\components\FileStorage::class
        );

        // Удаляем файл по полному пути с учётом file_path
        $storage->delete(
            $this->getResolvedPath(),
            $this->storage_type
        );

        return parent::beforeDelete();
    }
}
