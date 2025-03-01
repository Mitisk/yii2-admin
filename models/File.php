<?php

namespace Mitisk\Yii2Admin\models;

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
 * @property int $file_size
 * @property string $mime_type
 * @property string $path
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete() : bool
    {
        $file = \Yii::getAlias('@webroot') . str_replace('/web', '',$this->path); // Абсолютный путь к файлу

        if (file_exists($file)) {
            if (FileHelper::unlink($file)) {
                return true;
            } else {
                return false;
            }
        }

        return parent::beforeDelete();
    }
}
