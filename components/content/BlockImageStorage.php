<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\content;

use Mitisk\Yii2Admin\components\FileStorage;
use Mitisk\Yii2Admin\models\ContentBlock;
use Mitisk\Yii2Admin\models\File;
use yii\web\UploadedFile;

/**
 * Картинки блоков и страниц в общей таблице `file` (как у поля ImageField):
 * class_name = класс-владелец, item_id = id записи, field_name = поле.
 */
final class BlockImageStorage
{
    public const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly FileStorage $storage = new FileStorage(),
        private readonly string $ownerClass = ContentBlock::class,
    ) {
    }

    /**
     * Сохраняет загруженный файл и создаёт запись `file`.
     *
     * @return int Id записи `file`.
     * @throws \RuntimeException Недопустимое расширение или ошибка хранилища.
     */
    public function store(UploadedFile $file, int $blockId, string $field): int
    {
        $ext = strtolower((string)$file->extension);
        if (!in_array($ext, self::ALLOWED, true)) {
            throw new \RuntimeException('Недопустимый тип файла: .' . $ext);
        }

        $type = $this->storage->getStorageType();
        $filename = uniqid('', true) . '.' . $ext;
        if ($type === FileStorage::TYPE_S3) {
            $filename = 'content-block/' . $filename;
        }
        $saved = $this->storage->save($file->tempName, $filename);
        if ($saved === false) {
            throw new \RuntimeException('Не удалось сохранить файл «' . $file->name . '»');
        }

        $model = new File();
        $model->class_name = $this->ownerClass;
        $model->item_id = $blockId;
        $model->field_name = $field;
        $model->filename = $file->name;
        $model->file_size = $file->size;
        $model->mime_type = (string)$file->type;
        $model->storage_type = $type;
        $model->path = $type === FileStorage::TYPE_LOCAL ? '/web/' . ltrim($saved, '/') : $saved;
        if (!$model->save()) {
            $this->storage->delete($saved, $type);
            throw new \RuntimeException('Не удалось сохранить запись файла: ' . implode(' ', $model->getFirstErrors()));
        }
        return (int)$model->id;
    }

    /**
     * Удаляет запись `file` блока вместе с файлом. Чужие файлы не трогает.
     */
    public function delete(int $fileId): void
    {
        $file = File::find()->where(['id' => $fileId, 'class_name' => $this->ownerClass])->one();
        $file?->delete();
    }
}
