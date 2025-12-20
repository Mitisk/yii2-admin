<?php

namespace Mitisk\Yii2Admin\components;

use Aws\S3\S3Client;
use Mitisk\Yii2Admin\models\File; // Модель File
use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\FileHelper;

class StorageHelper extends Component
{
    const TYPE_LOCAL = 'local';
    const TYPE_FTP = 'ftp';
    const TYPE_S3 = 's3';

    // Категория настроек
    const SETTINGS_CAT = 'Mitisk\Yii2Admin\models\File';

    /**
     * Сохраняет файл в активное хранилище
     * * @param string $sourcePath Путь к временному файлу (tmp_name)
     * @param string $filename Желаемое имя файла (например, admin/avatar.jpg)
     * @return array ['path' => string, 'url' => string, 'type' => string]
     * @throws Exception
     */
    public static function saveFile(string $sourcePath, string $filename): array
    {
        $storageType = Yii::$app->settings->get(self::SETTINGS_CAT, 'storage_type') ?: self::TYPE_LOCAL;

        // Нормализация имени файла (убираем начальные слеши для S3/FTP ключей)
        $filename = ltrim($filename, '/\\');

        switch ($storageType) {
            case self::TYPE_S3:
                return self::saveToS3($sourcePath, $filename);
            case self::TYPE_FTP:
                return self::saveToFtp($sourcePath, $filename);
            case self::TYPE_LOCAL:
            default:
                return self::saveToLocal($sourcePath, $filename);
        }
    }

    /**
     * Удаляет файл
     */
    public static function deleteFile(string $path, string $type)
    {
        try {
            switch ($type) {
                case self::TYPE_S3:
                    $s3 = self::getS3Client();
                    $bucket = Yii::$app->settings->get(self::SETTINGS_CAT, 's3_bucket');
                    $s3->deleteObject([
                        'Bucket' => $bucket,
                        'Key'    => $path
                    ]);
                    break;
                case self::TYPE_FTP:
                    $conn = self::getFtpConnection();
                    @ftp_delete($conn, $path);
                    ftp_close($conn);
                    break;
                case self::TYPE_LOCAL:
                default:
                    $fsPath = Yii::getAlias('@webroot') . $path;
                    if (is_file($fsPath)) {
                        unlink($fsPath);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            Yii::error("Ошибка удаления файла ($type): " . $e->getMessage());
        }
    }

    /**
     * Логика S3
     */
    private static function saveToS3($source, $key): array
    {
        $s3 = self::getS3Client();
        $bucket = Yii::$app->settings->get(self::SETTINGS_CAT, 's3_bucket');
        $acl = 'public-read'; // Или private, если используете presigned URLs

        $result = $s3->putObject([
            'Bucket' => $bucket,
            'Key'    => $key,
            'SourceFile' => $source,
            'ACL'    => $acl,
        ]);

        return [
            'path' => $key,
            'url'  => $result['ObjectURL'],
            'type' => self::TYPE_S3
        ];
    }

    private static function getS3Client()
    {
        $region = Yii::$app->settings->get(self::SETTINGS_CAT, 's3_region');
        $endpoint = Yii::$app->settings->get(self::SETTINGS_CAT, 's3_endpoint'); // Custom endpoint
        $key = Yii::$app->settings->get(self::SETTINGS_CAT, 's3_key');
        $secret = Yii::$app->settings->get(self::SETTINGS_CAT, 's3_secret');

        $config = [
            'version' => 'latest',
            'region'  => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ];

        if ($endpoint) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = true; // Часто нужно для MinIO и некоторых провайдеров
        }

        return new S3Client($config);
    }

    /**
     * Логика FTP
     */
    private static function saveToFtp($source, $path): array
    {
        $conn = self::getFtpConnection();
        $ftpPath = Yii::$app->settings->get(self::SETTINGS_CAT, 'ftp_path') ?: '/';
        $fullPath = rtrim($ftpPath, '/') . '/' . $path;

        // Создаем директории рекурсивно, если их нет (упрощенно)
        $parts = explode('/', dirname($fullPath));
        $current = '';
        foreach ($parts as $part) {
            if (!$part) continue;
            $current .= '/' . $part;
            @ftp_mkdir($conn, $current);
        }

        if (ftp_put($conn, $fullPath, $source, FTP_BINARY)) {
            ftp_close($conn);

            // Формируем публичную ссылку
            $host = Yii::$app->settings->get(self::SETTINGS_CAT, 'ftp_public_domain'); // e.g., https://cdn.mysite.com
            $url = $host ? rtrim($host, '/') . $fullPath : $fullPath;

            return [
                'path' => $fullPath,
                'url'  => $url,
                'type' => self::TYPE_FTP
            ];
        }

        throw new Exception("Не удалось загрузить файл на FTP");
    }

    private static function getFtpConnection()
    {
        $host = Yii::$app->settings->get(self::SETTINGS_CAT, 'ftp_host');
        $user = Yii::$app->settings->get(self::SETTINGS_CAT, 'ftp_user');
        $pass = Yii::$app->settings->get(self::SETTINGS_CAT, 'ftp_pass');

        $conn = ftp_connect($host);
        if (!$conn || !ftp_login($conn, $user, $pass)) {
            throw new Exception("Не удалось подключиться к FTP");
        }
        ftp_pasv($conn, true);
        return $conn;
    }

    /**
     * Логика Local (Legacy совместимость)
     */
    private static function saveToLocal($source, $filename): array
    {
        // $filename приходит как "uploads/admin/file.png" или просто "file.png"
        // Если в начале нет /web, добавляем для совместимости путей Yii

        $webPath = '/web/' . ltrim($filename, '/');
        $fsPath = Yii::getAlias('@webroot') . str_replace('/web', '', $webPath);

        FileHelper::createDirectory(dirname($fsPath), 0775, true);

        if (copy($source, $fsPath)) { // copy вместо move, т.к. иногда source нужен еще раз
            // Удаляем исходник, если это был uploaded file (обычно move_uploaded_file делает это)
            // Но для универсальности оставим управление исходником вызывающему коду или используем rename
            @unlink($source);
        } else {
            throw new Exception("Не удалось сохранить файл локально");
        }

        return [
            'path' => $webPath, // Сохраняем старый формат пути /web/...
            'url'  => $webPath, // Локально url и path совпадают
            'type' => self::TYPE_LOCAL
        ];
    }
}