<?php

namespace Mitisk\Yii2Admin\components;

use Aws\S3\S3Client;
use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\FileHelper;

use Mitisk\Yii2Admin\models\File;

class FileStorage extends Component
{
    const TYPE_LOCAL = 'local';
    const TYPE_FTP = 'ftp';
    const TYPE_S3 = 's3';

    /**
     * @return string
     */
    public function getStorageType()
    {
        return Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'storage_type', self::TYPE_LOCAL);
    }

    /**
     * @param string $sourcePath Local path to the file to be saved
     * @param string $filename Desired filename
     * @return string|false The path relative to storage root or false on failure
     */
    public function save($sourcePath, $filename)
    {
        $type = $this->getStorageType();
        
        // Debug logging
        try {
            $logPath = Yii::getAlias('@webroot/storage_debug.log');
            $msg = date('Y-m-d H:i:s') . " - Save requested. Type: '$type'. Filename: '$filename'.\n";
            @file_put_contents($logPath, $msg, FILE_APPEND);
        } catch (\Exception $e) {}
        
        // Trim type to avoid whitespace issues
        $type = trim($type);

        switch ($type) {
            case self::TYPE_FTP:
                return $this->saveFtp($sourcePath, $filename);
            case self::TYPE_S3:
                return $this->saveS3($sourcePath, $filename);
            case self::TYPE_LOCAL:
            default:
                return $this->saveLocal($sourcePath, $filename);
        }
    }

    /**
     * @param string $path Path stored in DB
     * @param string $type Storage type (optional, defaults to current setting, but for deletion we might need the type the file was stored with)
     * @return bool
     */
    public function delete($path, $type = null)
    {
        if ($type === null) {
            $type = $this->getStorageType();
        }

        switch ($type) {
            case self::TYPE_FTP:
                return $this->deleteFtp($path);
            case self::TYPE_S3:
                return $this->deleteS3($path);
            case self::TYPE_LOCAL:
            default:
                return $this->deleteLocal($path);
        }
    }

    /**
     * @param string $path Path stored in DB
     * @param string $type Storage type
     * @return string|null Public URL to the file
     */
    public function getUrl($path, $type = null)
    {
        if ($type === null) {
            $type = $this->getStorageType();
        }

        switch ($type) {
            case self::TYPE_FTP:
                // FTP usually doesn't have a direct public URL unless mapped. 
                // We'll return null or a configured base URL if available.
                // Assuming for now user might have a web server mapping to FTP path or it's internal.
                // But typically FTP is just storage. If there's a public web view, we need a setting for it.
                $baseUrl = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'ftp_public_url');
                return $baseUrl ? rtrim($baseUrl, '/') . '/' . ltrim($path, '/') : null;
                
            case self::TYPE_S3:
                return $this->getUrlS3($path);

            case self::TYPE_LOCAL:
            default:
                $webroot = Yii::getAlias('@webroot');
                $web = Yii::getAlias('@web');
                // If path is absolute on disk, try to make it relative to webroot
                if (str_starts_with($path, $webroot)) {
                    return $web . substr($path, strlen($webroot));
                }
                // If path is already relative (e.g. uploads/file.jpg)
                return $web . '/' . ltrim($path, '/');
        }
    }

    /**
     * @param string $path Path stored in DB or relative path
     * @param string $type Storage type
     * @return bool
     */
    public function fileExists($path, $type = null)
    {
        if ($type === null) {
            $type = $this->getStorageType();
        }

        switch ($type) {
            case self::TYPE_FTP:
                return $this->existsFtp($path);
            case self::TYPE_S3:
                return $this->existsS3($path);
            case self::TYPE_LOCAL:
            default:
                return $this->existsLocal($path);
        }
    }

    // --- LOCAL ---

    protected function existsLocal($path)
    {
         $uploadDir = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'local_upload_dir', 'uploads/');
         $uploadDir = Yii::getAlias($uploadDir);
         // If path is absolute
         if (file_exists($path)) return true;
         // If path is relative to upload dir
         if (file_exists($uploadDir . '/' . $path)) return true;
         // If path is relative to webroot
         $webroot = Yii::getAlias('@webroot');
         if (file_exists($webroot . '/' . ltrim($path, '/'))) return true;

         return false;
    }

    protected function saveLocal($sourcePath, $filename)
    {
        $uploadDir = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'local_upload_dir', 'uploads/');
        $uploadDir = Yii::getAlias($uploadDir);
        
        if (!is_dir($uploadDir)) {
            FileHelper::createDirectory($uploadDir);
        }

        $targetPath = $uploadDir . '/' . $filename;
        
        // Ensure unique filename if needed (handled by FileUploader usually, but good to be safe)
        // For now, we assume filename is already prepared by FileUploader

        if (rename($sourcePath, $targetPath)) {
            // Return path relative to webroot if possible, or relative to app
            // Existing logic seems to store something like 'uploads/filename.jpg'
            return 'uploads/' . $filename; 
        }
        return false;
    }

    protected function deleteLocal($path)
    {
        $fullPath = $path;
        if (!file_exists($fullPath) && !str_starts_with($fullPath, '/') && !str_starts_with($fullPath, ':')) {
             $fullPath = Yii::getAlias('@webroot') . '/' . $path;
        }

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return true; // considered deleted if not exists
    }

    // --- FTP ---

    protected function getFtpConnection()
    {
        $host = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'ftp_host');
        $user = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'ftp_user');
        $pass = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'ftp_pass');
        $port = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'ftp_port', 21);

        if (!$host || !$user) return false;

        $conn = ftp_connect($host, $port);
        if ($conn && ftp_login($conn, $user, $pass)) {
            ftp_pasv($conn, true);
            return $conn;
        }
        return false;
    }

    protected function saveFtp($sourcePath, $filename)
    {
        $conn = $this->getFtpConnection();
        if (!$conn) return false;

        $basePath = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'ftp_path', '/');
        $remoteFile = rtrim($basePath, '/') . '/' . $filename;

        // Ensure directory exists - simplified, might fail if deep recursion needed
        $dir = dirname($remoteFile);
        if ($dir !== '.' && $dir !== '/') {
             @ftp_mkdir($conn, $dir); 
        }

        $success = ftp_put($conn, $remoteFile, $sourcePath, FTP_BINARY);
        ftp_close($conn);

        return $success ? $remoteFile : false;
    }

    protected function deleteFtp($path)
    {
        $conn = $this->getFtpConnection();
        if (!$conn) return false;

        $success = ftp_delete($conn, $path);
        ftp_close($conn);
        return $success;
    }

    protected function existsFtp($path)
    {
        $conn = $this->getFtpConnection();
        if (!$conn) return false;

        $basePath = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'ftp_path', '/');
        // Handle if path is full or relative
        // For simplicity, just check size for now as existence check
        $remoteFile = str_starts_with($path, '/') ? $path : rtrim($basePath, '/') . '/' . $path;

        $size = ftp_size($conn, $remoteFile);
        ftp_close($conn);

        return $size != -1;
    }

    // --- S3 ---

    protected function getS3Client()
    {
        $key = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_key');
        $secret = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_secret');
        $region = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_region', 'us-east-1');
        $endpoint = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_endpoint');
        
        if (!$key || !$secret) return null;

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
            $config['use_path_style_endpoint'] = (bool)Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_path_style', false);
        }

        return new S3Client($config);
    }

    protected function saveS3($sourcePath, $filename)
    {
        $s3 = $this->getS3Client();
        if (!$s3) return false;

        $bucket = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_bucket');
        $prefix = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_prefix', '');
        
        $key = ltrim($prefix . '/' . $filename, '/');

        try {
            $result = $s3->putObject([
                'Bucket' => $bucket,
                'Key'    => $key,
                'SourceFile' => $sourcePath,
                'ACL'    => 'public-read', // default to public read?
            ]);
            
            return $key;
        } catch (\Exception $e) {
            Yii::error('S3 Upload Error: ' . $e->getMessage());
            return false;
        }
    }

    protected function deleteS3($path)
    {
        $s3 = $this->getS3Client();
        if (!$s3) return false;

        $bucket = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_bucket');
        
        try {
            $s3->deleteObject([
                'Bucket' => $bucket,
                'Key'    => $path
            ]);
            return true;
        } catch (\Exception $e) {
            Yii::error('S3 Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    protected function existsS3($path)
    {
        $s3 = $this->getS3Client();
        if (!$s3) return false;

        $bucket = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_bucket');
        // Construct key same way as saveS3 if possible, but path usually comes from save return
        // If checking before save, path is just filename?
        // Logic: if path contains /, assume key. If simple filename, prepend prefix.
        
        $key = $path;
        if (!str_contains($path, '/')) {
             $prefix = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_prefix', '');
             $key = ltrim($prefix . '/' . $path, '/');
        }

        return $s3->doesObjectExist($bucket, $key);
    }

    protected function getUrlS3($path)
    {
        $s3 = $this->getS3Client();
        if (!$s3) return null;
        
        $bucket = Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 's3_bucket');
        return $s3->getObjectUrl($bucket, $path);
    }
}
