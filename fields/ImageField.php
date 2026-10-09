<?php

namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * Поле «Изображение с кадрированием»: одиночное изображение с клиентским
 * кропом (Cropper.js). Наследует всё хранение/удаление/валидацию у
 * {@see FileField} (таблица File, FileStorage, whitelist расширений).
 *
 * Кроп выполняется на клиенте: canvas → Blob подменяет содержимое файлового
 * инпута, дальше идёт обычная multipart-загрузка. Логика «одиночное
 * изображение: заменить при загрузке нового, иначе сохранить» реализована в
 * {@see save()} через синтез keep-list родителя.
 */
class ImageField extends FileField
{
    /** @var boolean Одиночное изображение */
    public $multiple = false;

    /** @var string[] Разрешены только изображения */
    public $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** @var string|null Соотношение сторон кропа ('16/9', '1', ...) или null (свободно) */
    public $aspectRatio;

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('image', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
        ]);
    }

    /**
     * @inheritdoc
     *
     * Одиночное изображение: если пришёл новый файл — заменяем (родитель
     * удалит старые записи и добавит новый); если файла нет — сохраняем
     * текущее изображение (передаём его id в keep-list).
     *
     * @return bool
     */
    public function save(): bool
    {
        $uploaded = UploadedFile::getInstances($this->model->getModel(), $this->name);

        $body = Yii::$app->request->getBodyParams();
        $fileUploader = ArrayHelper::getValue($body, 'FileUploader', []);
        if (!is_array($fileUploader)) {
            $fileUploader = [];
        }

        if (!empty($uploaded)) {
            // Новый файл → пустой keep-list: родитель удалит старое, добавит новое
            unset($fileUploader[$this->name]);
        } else {
            // Файла нет → сохраняем существующее изображение
            $existing = FieldsHelper::getFiles($this->model->getModel(), $this->name);
            if ($existing) {
                $keep = [];
                foreach ($existing as $file) {
                    $keep[] = ['id' => $file->id, 'alt' => (string)$file->alt_attribute];
                }
                $fileUploader[$this->name] = $keep;
            } else {
                unset($fileUploader[$this->name]);
            }
        }

        $body['FileUploader'] = $fileUploader;
        Yii::$app->request->setBodyParams($body);

        return parent::save();
    }
}
