<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use yii\base\Component;
use Yii;

class FileUploader extends Component
{
    private array $defaultOptions = [
        'limit' => null,
        'maxSize' => null,
        'fileMaxSize' => null,
        'extensions' => null,
        'disallowedExtensions' => ['htaccess', 'php', 'php3', 'php4', 'php5', 'phtml', 'ds_store'],
        'required' => false,
        'uploadDir' => 'uploads/',
        'title' => ['auto', 12],
        'replace' => false,
        'editor' => null,
        'listInput' => true,
        'files' => [],
        'move_uploaded_file' => null,
        'validate_file' => null,
    ];

    private $field = null;
    protected ?array $options = null;

    public function __construct($name, $options = null, $config = [])
    {
        parent::__construct($config);
        $this->defaultOptions['move_uploaded_file'] = function ($tmp, $dest, $item) {
            return move_uploaded_file($tmp, $dest);
        };
        $this->initialize($name, $options);
    }

    private function initialize(string $inputName, ?array $options): bool
    {
        $name = is_array($inputName) ? end($inputName) : $inputName;
        $_FilesName = is_array($inputName) ? $inputName[0] : $inputName;

        $this->options = $this->defaultOptions;
        if ($options !== null) {
            $this->options = array_merge($this->options, $options);
        }
        if (!is_array($this->options['files'])) {
            $this->options['files'] = [];
        }

        $this->field = [
            'name' => $name,
            'input' => null,
            'listInput' => $this->readListInput($name),
        ];

        if (isset($_FILES[$_FilesName])) {
            $this->field['input'] = $_FILES[$_FilesName];
            if (is_array($inputName)) {
                $arr = [];
                foreach ($this->field['input'] as $k => $v) {
                    $arr[$k] = $v[$inputName[1]];
                }
                $this->field['input'] = $arr;
            }

            if (!is_array($this->field['input']['name'])) {
                $this->field['input'] = array_merge($this->field['input'], [
                    "name" => [$this->field['input']['name']],
                    "tmp_name" => [$this->field['input']['tmp_name']],
                    "type" => [$this->field['input']['type']],
                    "error" => [$this->field['input']['error']],
                    "size" => [$this->field['input']['size']],
                ]);
            }

            foreach ($this->field['input']['name'] as $key => $value) {
                if (empty($value)) {
                    unset(
                        $this->field['input']['name'][$key],
                        $this->field['input']['type'][$key],
                        $this->field['input']['tmp_name'][$key],
                        $this->field['input']['error'][$key],
                        $this->field['input']['size'][$key]
                    );
                }
            }

            $this->field['count'] = count($this->field['input']['name']);
            return true;
        }

        return false;
    }

    public function getOptions(): array
    {
        return array_filter($this->options, fn($var) => gettype($var) != "object");
    }

    public function upload(): array
    {
        return $this->uploadFiles();
    }

    public function getFileList(?string $customKey = null): array
    {
        if ($customKey === null) {
            return $this->options['files'];
        }

        $result = [];
        foreach ($this->options['files'] as $key => $value) {
            $attribute = $this->getFileAttribute($value, $customKey);
            $result[] = $attribute ?: $value['file'];
        }

        return $result;
    }

    public function getUploadedFiles(): array
    {
        $result = [];
        foreach ($this->getFileList() as $key => $item) {
            if (isset($item['uploaded'])) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function getPreloadedFiles(): array
    {
        $result = [];
        foreach ($this->getFileList() as $key => $item) {
            if (!isset($item['uploaded'])) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function getRemovedFiles(string $customKey = 'file'): array
    {
        $removedFiles = [];

        if (
            isset($this->field['listInput']['list']) &&
            is_array($this->field['listInput']['list']) &&
            is_array($this->options['files'])
        ) {
            foreach ($this->options['files'] as $key => $value) {
                if (
                    !in_array($this->getFileAttribute($value, $customKey), $this->field['listInput']['list']) &&
                    (!isset($value['uploaded']) || !$value['uploaded'])
                ) {
                    $removedFiles[] = $value;
                    unset($this->options['files'][$key]);
                }
            }
        }

        if (is_array($this->options['files'])) {
            $this->options['files'] = array_values($this->options['files']);
        }

        return $removedFiles;
    }

    public function getListInput(): ?array
    {
        return $this->field['listInput'] ?? null;
    }

    private function getFileAttribute(array $item, string $attribute)
    {
        if (isset($item['data'][$attribute])) {
            return $item['data'][$attribute];
        }

        return $item[$attribute] ?? null;
    }

    private function readListInput(?string $name = null): ?array
    {
        $inputName = 'fileuploader-list-' . ($name ?: $this->field['name']);
        $input = isset($_POST[$inputName]) ? stripslashes($_POST[$inputName]) : null;

        if (is_string($this->options['listInput'])) {
            $inputName = $this->options['listInput'];
        }

        if ($input && $this->isJson($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $list = ['list' => [], 'values' => $decoded];

                foreach ($list['values'] as $key => $value) {
                    $list['list'][] = $value['file'];
                }

                return $list;
            }
        }

        return null;
    }

    private function validate(?array $item = null): bool|string
    {
        if ($item == null) {
            $ini = array(
                (boolean) ini_get('file_uploads'),
                (int) ini_get('upload_max_filesize'),
                (int) ini_get('post_max_size'),
                (int) ini_get('max_file_uploads'),
                (int) ini_get('memory_limit')
            );

            if (!$ini[0])
                return $this->codeToMessage('file_uploads');
            if ($this->options['required'] && strtolower($_SERVER['REQUEST_METHOD']) == "post" && $this->field['count'] + count($this->options['files']) == 0)
                return $this->codeToMessage('required_and_no_file');
            if (($this->options['limit'] && $this->field['count'] + count($this->options['files']) > $this->options['limit']) || ($ini[3] != 0 && ($this->field['count']) > $ini[3]))
                return $this->codeToMessage('max_number_of_files');
            $storageType = \Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'storage_type', 'local');
            if ($storageType === 'local') {
                if (!file_exists($this->options['uploadDir']) || !is_writable($this->options['uploadDir']))
                    return $this->codeToMessage('invalid_folder_path');
            }

            $total_size = 0; foreach($this->field['input']['size'] as $key=>$value){ $total_size += $value; } $total_size = $total_size/1000000;
            if ($ini[2] != 0 && $total_size > $ini[2])
                return $this->codeToMessage('post_max_size');
            if ($this->options['maxSize'] && $total_size > $this->options['maxSize'])
                return $this->codeToMessage('max_files_size');
        } else {
            if ($item['error'] > 0)
                return $this->codeToMessage($item['error'], $item);
            if (is_array($this->options['disallowedExtensions']) && (in_array(strtolower($item['extension']), $this->options['disallowedExtensions']) || preg_grep('/(' . $item['format'] . '\/\*|' . preg_quote($item['type'], '/') . ')/', $this->options['disallowedExtensions'])))
                return $this->codeToMessage('accepted_file_types', $item);
            if (is_array($this->options['extensions']) && !in_array(strtolower($item['extension']), $this->options['extensions']) && !preg_grep('/(' . $item['format'] . '\/\*|' . preg_quote($item['type'], '/') . ')/', $this->options['extensions']))
                return $this->codeToMessage('accepted_file_types', $item);
            if ($this->options['fileMaxSize'] && $item['size']/1000000 > $this->options['fileMaxSize'])
                return $this->codeToMessage('max_file_size', $item);
            if ($this->options['maxSize'] && $item['size']/1000000 > $this->options['maxSize'])
                return $this->codeToMessage('max_file_size', $item);
            $custom_validation = is_callable($this->options['validate_file']) ? $this->options['validate_file']($item, $this->options) : true;
            if ($custom_validation != true)
                return $custom_validation;
        }

        return true;
    }

    private function generateFilename(array|string $conf, array $item, bool $skipReplaceCheck = false): string
    {
        if (is_callable($conf))
            $conf = $conf($item);

        $conf = !is_array($conf) ? array($conf) : $conf;
        $type = $conf[0];
        $length = isset($conf[1]) ? max(1, (int) $conf[1]) : 12;
        $forceExtension = isset($conf[2]) && $conf[2] == true;
        $random_string = $this->randomString($length);
        $extension = !empty($item['extension']) ? '.' . $item['extension'] : '';
        $string = '';

        switch($type) {
            case null:
            case "auto":
                $string = $random_string;
                break;
            case "name":
                $string = $item['title'];
                break;
            default:
                $string = $type;
                $string_extension = substr(strrchr($string, "."), 1);

                $string = str_replace("{i}", $item['i'] + 1, $string);
                $string = str_replace("{random}", $random_string, $string);
                $string = str_replace("{file_name}", $item['title'], $string);
                $string = str_replace("{file_size}", $item['size'], $string);
                $string = str_replace("{timestamp}", time(), $string);
                $string = str_replace("{date}", date('Y-n-d_H-i-s'), $string);
                $string = str_replace("{extension}", $item['extension'], $string);
                $string = str_replace("{format}", $item['format'], $string);
                $string = str_replace("{index}", isset($item['listProps']['index']) ? $item['listProps']['index'] : 0, $string);

                if ($forceExtension && !empty($string_extension)) {
                    if ($string_extension != "{extension}") {
                        $type = substr($string, 0, -(strlen($string_extension) + 1));
                        $extension = $item['extension'] = $string_extension;
                    } else {
                        $type = substr($string, 0, -(strlen($item['extension']) + 1));
                        $extension = '';
                    }
                }
        }

        if ($extension && !preg_match('/'.$extension.'$/', $string))
            $string .= $extension;

        // generate another filename if a file with the same name already exists
        // only when replace options is true
        if (!$this->options['replace'] && !$skipReplaceCheck) {
            $title = $item['title'];
            $i = 1;
            
            $storageType = \Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'storage_type', 'local');
            $storage = $storageType !== 'local' ? new FileStorage() : null;

            while (($storageType === 'local' && file_exists($this->options['uploadDir'] . $string)) || ($storage && $storage->fileExists($string))) {
                $item['title'] = $title . " ({$i})";
                $conf[0] = $type == "auto" || $type == "name" || strpos($string, "{random}") !== false ? $type : $type  . " ({$i})";
                $string = $this->generateFileName($conf, $item, true);
                $i++;
            }
        }

        return self::filterFilename($string);
    }

    public static function filterFilename(string $filename): string
    {
        $delimiter = '_';
        $invalidChars = array_merge(range("\x00", "\x1F"), ['<', '>', ':', '"', '/', '\\', '|', '?', '*']);
        $filename = str_replace($invalidChars, $delimiter, $filename);
        $filename = preg_replace('/(' . preg_quote($delimiter, '/') . '){2,}/', '$1', $filename);
        return $filename;
    }

    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function randomString(int $length = 12): string
    {
        return substr(str_shuffle("_0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes > 0) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return '0 bytes';
        }
    }

    public function codeToMessage($code, ?array $file = null): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return Yii::t('app', 'The uploaded file exceeds the upload_max_filesize directive in php.ini');
            case UPLOAD_ERR_FORM_SIZE:
                return Yii::t('app', 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form');
            case UPLOAD_ERR_PARTIAL:
                return Yii::t('app', 'The uploaded file was only partially uploaded');
            case UPLOAD_ERR_NO_FILE:
                return Yii::t('app', 'No file was uploaded');
            case UPLOAD_ERR_NO_TMP_DIR:
                return Yii::t('app', 'Missing a temporary folder');
            case UPLOAD_ERR_CANT_WRITE:
                return Yii::t('app', 'Failed to write file to disk');
            case UPLOAD_ERR_EXTENSION:
                return Yii::t('app', 'File upload stopped by extension');
            case 'accepted_file_types':
                return Yii::t('app', 'File type is not allowed for {file}', ['file' => $file['old_name']]);
            case 'file_uploads':
                return Yii::t('app', 'File uploading option in disabled in php.ini');
            case 'max_file_size':
                return Yii::t('app', '{file} is too large', ['file' => $file['old_name']]);
            case 'max_files_size':
                return Yii::t('app', 'Files are too big');
            case 'max_number_of_files':
                return Yii::t('app', 'Maximum number of files is exceeded');
            case 'required_and_no_file':
                return Yii::t('app', 'No file was chosen. Please select one');
            case 'invalid_folder_path':
                return Yii::t('app', 'Upload folder doesn\'t exist or is not writable');
            default:
                return Yii::t('app', 'Unknown upload error');
        }
    }

    public static function resize(string $filename, ?int $width = null, ?int $height = null, ?string $destination = null, $crop = false, int $quality = 95, int $rotation = 0): ?array
    {
        if (!is_file($filename) || !is_readable($filename))
            return false;

        $source = null;
        $destination = !$destination ? $filename : $destination;
        if (file_exists($destination) && !is_writable($destination))
            return false;
        $imageInfo = @getimagesize($filename);
        if (!$imageInfo)
            return false;
        $exif = function_exists('exif_read_data') ? @exif_read_data($filename) : array();

        // detect actions
        $hasRotation = $rotation || isset($exif['Orientation']);
        $hasCrop = is_array($crop) || $crop == true;
        $hasResizing = $width || $height;

        if (!$hasRotation && !$hasCrop && !$hasResizing && (!isset($exif['Orientation']) || $exif['Orientaiton'] == 1) && $filename == $destination)
            return false;

        // store image information
        list ($imageWidth, $imageHeight, $imageType) = $imageInfo;
        $imageRatio = $imageWidth / $imageHeight;

        // create GD image
        switch($imageType) {
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filename);
                break;
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filename);
                break;
            default:
                return false;
        }

        // rotation
        if ($hasRotation) {
            $cacheWidth = $imageWidth;
            $cacheHeight = $imageHeight;

            // exif rotation
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 2:
                        imageflip($source, IMG_FLIP_HORIZONTAL);
                        break;
                    case 3:
                        $source = imagerotate($source, 180, 0);
                        break;
                    case 4:
                        $source = imagerotate($source, 180, 0);
                        imageflip($source, IMG_FLIP_HORIZONTAL);
                        break;
                    case 5:
                        $imageWidth = $cacheHeight;
                        $imageHeight = $cacheWidth;

                        $source = imagerotate($source, -90, 0);
                        imageflip($source, IMG_FLIP_HORIZONTAL);
                        break;
                    case 6:
                        $imageWidth = $cacheHeight;
                        $imageHeight = $cacheWidth;

                        $source = imagerotate($source, -90, 0);
                        break;
                    case 7:
                        $imageWidth = $cacheHeight;
                        $imageHeight = $cacheWidth;

                        $source = imagerotate($source, 90, 0);
                        imageflip($source, IMG_FLIP_HORIZONTAL);
                        break;
                    case 8:
                        $imageWidth = $cacheHeight;
                        $imageHeight = $cacheWidth;

                        $source = imagerotate($source, 90, 0);
                        break;
                }

                $cacheWidth = $imageWidth;
                $cacheHeight = $imageHeight;
            }

            // param rotation
            if ($rotation == 90 || $rotation == 270) {
                $imageWidth = $cacheHeight;
                $imageHeight = $cacheWidth;
            }
            $rotation = $rotation * -1;
            $source = imagerotate($source, $rotation, 0);
        }

        // crop
        $crop = array_merge(array(
            'left' => 0,
            'top' => 0,
            'width' => $imageWidth,
            'height' => $imageHeight,
            '_paramCrop' => $crop
        ), is_array($crop) ? $crop : array());
        if (is_array($crop['_paramCrop'])) {
            $crop['left'] = floor($crop['_paramCrop']['left']);
            $crop['top'] = floor($crop['_paramCrop']['top']);
            $crop['width'] = floor($crop['_paramCrop']['width']);
            $crop['height'] = floor($crop['_paramCrop']['height']);
        }

        // set default $width and $height
        $width = !$width ? $crop['width'] : $width;
        $height = !$height ? $crop['height'] : $height;
        $ratio = $width/$height;

        // resize
        if ($crop['_paramCrop'] === true) {
            if ($imageRatio >= $ratio) {
                $crop['newWidth'] = floor($crop['width'] / ($crop['height'] / $height));
                $crop['newHeight'] = $height;
            } else {
                $crop['newHeight'] = floor($crop['height'] / ($crop['width'] / $width));
                $crop['newWidth'] = $width;
            }

            $crop['left'] = floor(0 - ($crop['newWidth'] - $width) / 2);
            $crop['top'] = floor(0 - ($crop['newHeight'] - $height) / 2);

            if ($crop['width'] < $width || $crop['height'] < $height) {
                $crop['left'] = $crop['width'] < $width ? floor($width/2 - $crop['width']/2) : 0;
                $crop['top'] = $crop['height'] < $height ? floor($height/2 - $crop['height']/2) : 0;
                $crop['newWidth'] = $crop['width'];
                $crop['newHeight'] = $crop['height'];
            }
        } elseif ($crop['width'] < $width && $crop['height'] < $height) {
            $width = $crop['width'];
            $height = $crop['height'];
        } else {
            $newRatio = $crop['width'] / $crop['height'];

            if ($ratio > $newRatio) {
                $width = floor($height * $newRatio);
            } else {
                $height = floor($width / $newRatio);
            }
        }

        // save
        $dest = null;
        $destExt = strtolower(substr($destination, strrpos($destination, '.') + 1));
        if (pathinfo($destination, PATHINFO_EXTENSION)) {
            if (in_array($destExt, array('gif', 'jpg', 'jpeg', 'png'))) {
                if ($destExt == 'gif')
                    $imageType = IMAGETYPE_GIF;
                if ($destExt == 'jpg' || $destExt == 'jpeg')
                    $imageType = IMAGETYPE_JPEG;
                if ($destExt == 'png')
                    $imageType = IMAGETYPE_PNG;
            }
        } else {
            $imageType = IMAGETYPE_JPEG;
            $destination .= '.jpg';
        }
        switch($imageType) {
            case IMAGETYPE_GIF:
                $dest = imagecreatetruecolor((int)$width, (int)$height);
                $background = imagecolorallocatealpha($dest, 255, 255, 255, 1);
                imagecolortransparent($dest, $background);
                imagefill($dest, 0, 0 , $background);
                imagesavealpha($dest, true);
                break;
            case IMAGETYPE_JPEG:
                $dest = imagecreatetruecolor((int)$width, (int)$height);
                $background = imagecolorallocate($dest, 255, 255, 255);
                imagefilledrectangle($dest, 0, 0, (int)$width, (int)$height, $background);
                break;
            case IMAGETYPE_PNG:
                if (!imageistruecolor($source)) {
                    $dest = imagecreate((int)$width, (int)$height);
                    $background = imagecolorallocatealpha($dest, 255, 255, 255, 1);
                    imagecolortransparent($dest, $background);
                    imagefill($dest, 0, 0 , $background);
                } else {
                    $dest = imagecreatetruecolor((int)$width, (int)$height);
                }
                imagealphablending($dest, false);
                imagesavealpha($dest, true);
                break;
            default:
                return false;
        }

        imageinterlace($dest, true);

        imagecopyresampled(
            $dest,
            $source,
            isset($crop['newWidth']) ? $crop['left'] : 0,
            isset($crop['newHeight']) ? $crop['top'] : 0,
            !isset($crop['newWidth']) ? $crop['left'] : 0,
            !isset($crop['newHeight']) ? $crop['top'] : 0,
            isset($crop['newWidth']) ? (int)$crop['newWidth'] : (int)$width,
            isset($crop['newHeight']) ? (int)$crop['newHeight'] : (int)$height,
            (int)$crop['width'],
            (int)$crop['height']
        );

        switch ($imageType) {
            case IMAGETYPE_GIF:
                imagegif($dest, $destination);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($dest, $destination, (int)$quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($dest, $destination, (int)(10-$quality/10));
                break;
        }

        imagedestroy($source);
        imagedestroy($dest);
        clearstatcache(true, $destination);

        return array(
            'width' => round(isset($crop['newWidth']) ? $crop['newWidth'] : $width),
            'height' => round(isset($crop['newHeight']) ? $crop['newHeight'] : $height),
            'type' => $destExt
        );
    }

    private function uploadFiles(): array
    {
        $data = array(
            "hasWarnings" => false,
            "isSuccess" => false,
            "warnings" => array(),
            "files" => array()
        );
        $listInput = $this->field['listInput'];
        $uploadDir = str_replace(getcwd() . '/', '', $this->options['uploadDir']);
        $chunk = isset($_POST['_chunkedd']) && count($this->field['input']['name']) == 1 ? json_decode(stripslashes($_POST['_chunkedd']), true) : false;

        if ($this->field['input']) {
            // validate ini settings and some generally options
            $validate = $this->validate();
            $data['isSuccess'] = true;

            if ($validate === true) {
                // process the files
                $count = count($this->field['input']['name']);
                for($i = 0; $i < $count; $i++) {
                    $file = array(
                        'name' => $this->field['input']['name'][$i],
                        'tmp_name' => $this->field['input']['tmp_name'][$i],
                        'type' => $this->field['input']['type'][$i],
                        'error' => $this->field['input']['error'][$i],
                        'size' => $this->field['input']['size'][$i]
                    );

                    // chunk
                    if ($chunk) {
                        if (isset($chunk['isFirst']))
                            $chunk['temp_name'] = $this->randomString(6) . time();

                        $tmp_name = $uploadDir . '.unconfirmed_' . self::filterFilename($chunk['temp_name']);
                        if (!isset($chunk['isFirst']) && !file_exists($tmp_name))
                            continue;
                        $sp = fopen($file['tmp_name'], 'rb');
                        $op = fopen($tmp_name, isset($chunk['isFirst']) ? 'wb' : 'ab');
                        while (!feof($sp)) {
                            $buffer = fread($sp, 512);
                            fwrite($op, $buffer);
                        }

                        // close handles
                        fclose($op);
                        fclose($sp);

                        if (isset($chunk['isLast'])) {
                            $file['tmp_name'] = $tmp_name;
                            $file['name'] = $chunk['name'];
                            $file['type'] = $chunk['type'];
                            $file['size'] = $chunk['size'];
                        } else {
                            echo json_encode(array(
                                'fileuploader' => array(
                                    'temp_name' => $chunk['temp_name']
                                )
                            ));
                            exit;
                        }
                    }

                    $metas = array();
                    $metas['tmp_name'] = $file['tmp_name'];
                    $metas['extension'] = strtolower(substr(strrchr($file['name'], "."), 1));
                    $metas['type'] = $file['type'];
                    $metas['format'] = strtok($file['type'], '/');
                    $metas['name'] = $metas['old_name'] = $file['name'];
                    $metas['title'] = $metas['old_title'] = substr($metas['old_name'], 0, (strlen($metas['extension']) > 0 ? -(strlen($metas['extension'])+1) : strlen($metas['old_name'])));
                    $metas['size'] = $file['size'];
                    $metas['size2'] = $this->formatSize($file['size']);
                    $metas['date'] = date('r');
                    $metas['error'] = $file['error'];
                    $metas['chunked'] = $chunk;

                    // validate file
                    $validateFile = $this->validate(array_diff_key($metas, array_flip(array('tmp_name', 'chunked'))));

                    // check if file is in listInput
                    $listInputName = '0:/' . $metas['old_name'];
                    $fileInList = $listInput === null || in_array($listInputName, $listInput['list']);

                    // add file to memory
                    if ($validateFile === true) {
                        if ($fileInList) {
                            $fileListIndex = 0;

                            if ($listInput) {
                                $fileListIndex = array_search($listInputName, $listInput['list']);
                                $metas['listProps'] = $listInput['values'][$fileListIndex];
                                unset($listInput['list'][$fileListIndex]);
                                unset($listInput['values'][$fileListIndex]);
                            }

                            $metas['i'] = count($data['files']);
                            $metas['name'] = $this->generateFileName($this->options['title'], array_diff_key($metas, array_flip(array('tmp_name', 'error', 'chunked'))));
                            $metas['title'] = substr($metas['name'], 0, (strlen($metas['extension']) > 0 ? -(strlen($metas['extension'])+1) : strlen($metas['name'])));
                            $metas['file'] = $uploadDir . $metas['name'];
                            $metas['replaced'] = file_exists($metas['file']);

                            ksort($metas);
                            $data['files'][] = $metas;
                        }
                    } else {
                        if ($metas['chunked'] && file_exists($metas['tmp_name']))
                            unlink($metas['tmp_name']);
                        if (!$fileInList)
                            continue;

                        $data['isSuccess'] = false;
                        $data['hasWarnings'] = true;
                        $data['warnings'][] = $validateFile;
                        $data['files'] = array();

                        break;
                    }
                }

                // upload the files
                if (!$data['hasWarnings']) {
                    foreach($data['files'] as $key => $file) {
                        $isFileSaved = false;
                        $storageType = \Yii::$app->settings->get('Mitisk\Yii2Admin\models\File', 'storage_type', 'local');
                        
                        // Use FileStorage for non-local or if configured
                        if ($storageType !== 'local') {
                            $storage = new FileStorage();
                            // Use the generated name (not tmp_name)
                            // $file['name'] contains the generated/safe filename
                            $savedPath = $storage->save($file['tmp_name'], $file['name']);
                            if ($savedPath !== false) {
                                $data['files'][$key]['file'] = $savedPath;
                                $isFileSaved = true;
                                @unlink($file['tmp_name']);
                            }
                        } else {
                             // Existing local logic
                             $isFileSaved = $file['chunked'] ? rename($file['tmp_name'], $file['file']) : $this->options['move_uploaded_file']($file['tmp_name'], $file['file'], $file);
                        }

                        if ($isFileSaved) {
                            unset($data['files'][$key]['i']);
                            unset($data['files'][$key]['chunked']);
                            unset($data['files'][$key]['error']);
                            unset($data['files'][$key]['tmp_name']);
                            $data['files'][$key]['uploaded'] = true;

                            $this->options['files'][] = $data['files'][$key];
                        } else {
                            unset($data['files'][$key]);
                        }
                    }
                }
            } else {
                $data['isSuccess'] = false;
                $data['hasWarnings'] = true;
                $data['warnings'][] = $validate;
            }
        } else {
            $lastPHPError = error_get_last();
            if ($lastPHPError && $lastPHPError['type'] == E_WARNING && $lastPHPError['line'] == 0) {
                $errorMessage = null;

                if (strpos($lastPHPError['message'], "POST Content-Length") !== false)
                    $errorMessage = $this->codeToMessage(UPLOAD_ERR_INI_SIZE);
                if (strpos($lastPHPError['message'], "Maximum number of allowable file uploads") !== false)
                    $errorMessage = $this->codeToMessage('max_number_of_files');

                if ($errorMessage != null) {
                    $data['isSuccess'] = false;
                    $data['hasWarnings'] = true;
                    $data['warnings'][] = $errorMessage;
                }

            }

            if ($this->options['required'] && strtolower($_SERVER['REQUEST_METHOD']) == "post") {
                $data['hasWarnings'] = true;
                $data['warnings'][] = $this->codeToMessage('required_and_no_file');
            }
        }

        // add listProp attribute to the files
        if ($listInput)
            foreach($this->getFileList() as $key=>$item) {
                if (!isset($item['listProps'])) {
                    $fileListIndex = array_search($item['file'], $listInput['list']);

                    if ($fileListIndex !== false) {
                        $this->options['files'][$key]['listProps'] = $listInput['values'][$fileListIndex];
                    }
                }

                if (isset($item['listProps'])) {
                    unset($this->options['files'][$key]['listProps']['file']);

                    if (empty($this->options['files'][$key]['listProps']))
                        unset($this->options['files'][$key]['listProps']);
                }
            }

        $data['files'] = $this->getUploadedFiles();

        // call file editor
        $this->editFiles();

        // call file sorter
        $this->sortFiles();

        $data['files'] = $this->getUploadedFiles();

        return $data;
    }

    /**
     * editFiles method
     * Edit all files that have an editor from Front-End
     *
     * @private
     * @return void
     */
    protected function editFiles() {
        if ($this->options['editor'] === false)
            return;

        foreach($this->getFileList() as $key=>$item) {
            $file = !isset($item['relative_file']) ? $item['file'] : $item['relative_file'];

            // add editor to files
            if (isset($item['listProps']) && isset($item['listProps']['editor'])) {
                $item['editor'] = $item['listProps']['editor'];
            }
            if (isset($item['uploaded']) && isset($_POST['_editorr']) && $this->isJSON(stripcslashes($_POST['_editorr'])) && count($this->field['input']['name']) == 1) {
                $item['editor'] = json_decode(stripslashes($_POST['_editorr']), true);
            }

            // edit file
            if (file_exists($file) && strpos($item['type'], 'image/') === 0) {
                $width = isset($this->options['editor']['maxWidth']) ? $this->options['editor']['maxWidth'] : null;
                $height = isset($this->options['editor']['maxHeight']) ? $this->options['editor']['maxHeight'] : null;
                $quality = isset($this->options['editor']['quality']) ? $this->options['editor']['quality'] : 90;
                $rotation = isset($item['editor']['rotation']) ? $item['editor']['rotation'] : 0;
                $crop = isset($this->options['editor']['crop']) ? $this->options['editor']['crop'] : false;
                $crop = isset($item['editor']['crop']) ? $item['editor']['crop'] : $crop;

                // edit
                $this->options['files'][$key]['image'] = self::resize($file, $width, $height, null, $crop, $quality, $rotation);
                $this->options['files'][$key]['size'] = filesize($file);
                if (isset($this->options['files'][$key]['size2']))
                    $this->options['files'][$key]['size2'] = $this->formatSize($this->options['files'][$key]['size']);
            }
        }
    }

    /**
     * sortFiles method
     * Sort all files that have an index from Front-End
     *
     * @private
     * @return void
     */
    private function sortFiles() {
        foreach($this->options['files'] as $key=>$item) {
            if (isset($item['listProps']) && isset($item['listProps']['index']))
                $this->options['files'][$key]['index'] = $item['listProps']['index'];
        }

        $freeIndex = count($this->options['files']);
        if(isset($this->options['files'][0]['index']))
            usort($this->options['files'], function($a, $b) {
                global $freeIndex;

                if (!isset($a['index'])) {
                    $a['index'] = $freeIndex;
                    $freeIndex++;
                }

                if (!isset($b['index'])) {
                    $b['index'] = $freeIndex;
                    $freeIndex++;
                }

                return $a['index'] - $b['index'];
            });
    }
}