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
 * @property string $external_link
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
            [['path', 'external_link'], 'string', 'max' => 1000],
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
            'external_link' => 'Внешняя ссылка',
            'storage_type' => 'Тип хранилища',
        ];
    }

    public function getUrl()
    {
        $storage = \Yii::createObject(\Mitisk\Yii2Admin\components\FileStorage::class);
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
                $webroot = \Yii::getAlias('@webroot');
                // If path is relative like 'uploads/img.jpg'
                $candidate = $webroot . '/' . ltrim($this->path, '/');
                if (is_file($candidate)) {
                    $localPath = $candidate;
                }
             }
        }
        
        return FieldsHelper::isImageFile($localPath, $publicPath);
    }

    public function generateFileUploaderData($inputName = null) : array
    {
        $storage = \Yii::createObject(\Mitisk\Yii2Admin\components\FileStorage::class);
        $url = $storage->getUrl($this->path, $this->storage_type);
        
        return [
            'name' => $this->filename,
            'file' => $url ?: $this->path, // Provide full URL if possible
            'type' => $this->mime_type,
            'size' => (int)$this->file_size,
            'data' => [
                'file_id'    => (int)$this->id,
                'field_name' => $inputName,
                'alt'        => $this->alt_attribute,
                ]
            ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete() : bool
    {
        $storage = \Yii::createObject(\Mitisk\Yii2Admin\components\FileStorage::class);
        
        // Try to delete file from storage
        // We ignore failure here to allow DB record deletion even if file is missing
        $storage->delete($this->path, $this->storage_type);

        return parent::beforeDelete();
    }
}
