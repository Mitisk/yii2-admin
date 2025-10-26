<?php
namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\AdminComponent;
use Yii;
use yii\base\Component;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use ZipArchive;

/**
 * Хелпер для управления компонентами
 * Взаимодействует с API для загрузки и управления компонентами
 */
class ComponentHelper extends Component
{
    /**
     * @var string URL API сервера
     */
    public $apiUrl = 'https://api.keypage.ru';

    /**
     * @var string API ключ сайта
     */
    public $apiKey;

    /**
     * @var string URL текущего сайта
     */
    public $siteUrl;

    /**
     * @var string Путь для установки компонентов
     */
    public $componentsPath = '@app/components';

    /**
     * @var array Кэш загруженных компонентов
     */
    private $componentsCache = [];

    /** @var bool Разрешение на использование компонента */
    public bool $isEnabled = true;

    /**
     * Инициализация компонента
     */
    public function init()
    {
        parent::init();

        // Получаем настройки из конфигурации
        if (empty($this->apiKey)) {
            $this->apiKey = Yii::$app->settings->get('GENERAL', 'api_key') ?? null;
        }

        if (empty($this->siteUrl)) {
            $this->siteUrl = Yii::$app->request->hostInfo;
        }

        if (!($this->apiKey) || !($this->siteUrl)) {
            $this->isEnabled = false;
        }
    }

    /**
     * Получение списка всех доступных компонентов
     * @return array
     */
    public function getAvailableComponents()
    {
        $response = $this->makeApiRequest('components', 'GET');
        return $response['data'] ?? [];
    }

    /**
     * Проверка существования компонента
     * @param string $componentName
     * @return bool
     */
    public function hasComponent($componentName)
    {
        $componentPath = $this->getComponentPath($componentName);
        if (file_exists($componentPath . '/Component.php')) {
            $class = $this->getNamespace($componentName) . '\\Component';
            return $class::isEnabled();
        }
        return false;
    }

    /**
     * Загрузка и установка компонента
     * @param string $componentName
     * @param string|null $version
     * @return bool|array
     */
    public function installComponent(string $componentName, string|null $version = null) : bool|array
    {
        try {
            // Получаем информацию о компоненте
            $componentInfo = $this->getComponentInfo($componentName);
            if (!$componentInfo) {
                throw new \Exception("Компонент '$componentName' не найден");
            }

            // Загружаем файлы компонента
            $params = [
                'component' => $componentInfo['alias']
            ];

            if ($version) {
                $params['version'] = $version;
            }

            $response = $this->makeApiRequest('components/install', 'GET', $params);

            // Создаем временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'component_');
            file_put_contents($tempFile, $response);

            // Извлекаем архив
            $componentPath = $this->getComponentPath($componentName);
            $this->extractArchive($tempFile, $componentPath);

            // Удаляем временный файл
            unlink($tempFile);

            // Проверяем наличие install-скриптов
            $this->install($componentName, $componentPath);
            $this->update($componentName, $componentPath);

            // Удаляем скрипты
            $this->deleteInstallScript($componentPath);

            $this->replaceNamespacePlaceholders($componentPath, $componentName);

            Yii::info("Компонент '$componentName' успешно установлен");
            return $componentInfo;

        } catch (\Exception $e) {
            Yii::error("Ошибка установки компонента '$componentName': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновление компонента
     * @param string $componentName
     * @return bool|array
     */
    public function updateComponent(string $componentName) : bool|array
    {
        try {
            // Получаем информацию о компоненте
            $componentInfo = $this->getComponentInfo($componentName);
            if (!$componentInfo) {
                throw new \Exception("Компонент '$componentName' не найден");
            }

            // Загружаем файлы компонента
            $params = [
                'component' => $componentInfo['alias']
            ];

            $response = $this->makeApiRequest('components/install', 'GET', $params);

            // Создаем временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'component_');
            file_put_contents($tempFile, $response);

            // Извлекаем архив
            $componentPath = $this->getComponentPath($componentName);

            // Распаковываем архив **с перезаписью**, без удаления файлов вне архива
            $this->extractArchiveIncremental($tempFile, $componentPath);

            // Удаляем временный файл
            unlink($tempFile);

            // Проверяем наличие update-скрипта
            $this->update($componentName, $componentPath);
            // Удаляем скрипты
            $this->deleteInstallScript($componentPath);

            $this->replaceNamespacePlaceholders($componentPath, $componentName);

            Yii::info("Компонент '$componentName' успешно установлен");
            return $componentInfo;

        } catch (\Exception $e) {
            Yii::error("Ошибка установки компонента '$componentName': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаление компонента
     *
     * @param string $componentName - Alias компонента
     * @return bool
     */
    public function uninstallComponent(string $componentName) : bool
    {
        try {
            // Получаем информацию о компоненте
            $componentInfo = $this->getComponentInfo($componentName);
            if (!$componentInfo) {
                throw new \Exception("Компонент '$componentName' не найден");
            }

            $componentPath = $this->getComponentPath($componentName);

            // Проверяем наличие uninstall-скрипта
            $uninstallScript = $componentPath . DIRECTORY_SEPARATOR . 'uninstall.php';
            if (file_exists($uninstallScript)) {
                // Выполняем uninstall-скрипт
                try {
                    include $uninstallScript;
                } catch (\Throwable $e) {
                    Yii::error("Ошибка при выполнении uninstall.php для компонента '$componentName': " . $e->getMessage());
                    throw new \Exception("Не удалось корректно удалить компонент '$componentName'");
                }
            }

            // Удаляем директорию компонента рекурсивно
            $this->removeDirectory($componentPath);

            return true;
        } catch (\Exception $e) {
            Yii::error("Ошибка установки компонента '$componentName': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Выполнение установки компонента из файла
     * @param string $componentName - Alias компонента
     * @param string $componentPath - Путь к директории компонента
     * @return void
     * @throws \Exception
     */
    private function install(string $componentName, string $componentPath) : void
    {
        // Проверяем наличие install-скрипта
        $installScript = $componentPath . DIRECTORY_SEPARATOR . 'install.php';
        if (file_exists($installScript)) {
            // Выполняем install-скрипт
            try {
                include $installScript;
            } catch (\Throwable $e) {
                Yii::error("Ошибка при выполнении install.php для компонента '$componentName': " . $e->getMessage());
                throw new \Exception("Не удалось корректно установить компонент '$componentName'");
            }
        }
    }

    /**
     * Удаление скриптов установки и обновления компонента
     * @param string $componentPath - Путь к директории компонента
     * @return void
     */
    private function deleteInstallScript(string $componentPath) : void
    {
        $installScript = $componentPath . DIRECTORY_SEPARATOR . 'install.php';
        if (file_exists($installScript)) {
            unlink($installScript);
        }

        $updateScript = $componentPath . DIRECTORY_SEPARATOR . 'update.php';
        if (file_exists($updateScript)) {
            unlink($updateScript);
        }
    }

    /**
     * Выполнение обновления компонента из файла
     * @param string $componentName - Alias компонента
     * @param string $componentPath - Путь к директории компонента
     * @return void
     * @throws \Exception
     */
    private function update(string $componentName, string $componentPath) : void
    {
        $updateScript = $componentPath . DIRECTORY_SEPARATOR . 'update.php';
        if (file_exists($updateScript)) {
            try {
            include $updateScript;
            } catch (\Throwable $e) {
                Yii::error("Ошибка при выполнении update.php для компонента '$componentName': " . $e->getMessage());
                throw new \Exception("Не удалось корректно обновить компонент '$componentName'");
            }
        }
    }

    /**
     * Namespace компонента
     * @param $componentName
     * @return string
     */
    public function getNamespace($componentName) : string
    {
        // Получаем абсолютный путь из алиаса
        $path = Yii::getAlias($this->componentsPath);
        // Определяем базовый неймспейс (последняя часть пути с большой буквы)
        $base = basename($path);
        // Формируем неймспейс, например: app\components\mycomponent
        return 'app\\' . $base . '\\' . $componentName;
    }

    /**
     * Отдает права валидации для модели
     * @param string $componentName - Alias компонента
     * @param array $rules - Массив правил
     * @param ActiveRecord $model - Модель
     * @return void
     */
    public function getRules(string $componentName, array &$rules, ActiveRecord $model) : void
    {
        $componentClass = $this->getNamespace($componentName) . '\\Component';
        if (class_exists($componentClass)) {
            $componentClass::rules($rules, $model);
        }
    }

    /**
     * Отдает массив названий полей
     * @param string $componentName
     * @param array $labels
     * @return void
     */
    public function getLabels(string $componentName, array &$labels) : void
    {
        $componentClass = $this->getNamespace($componentName) . '\\Component';
        if (class_exists($componentClass)) {
            $componentClass::labels($labels);
        }
    }

    /**
     * Рекурсивно обходит директорию и заменяет [NAMESPACE] на заданное в каждом PHP-файле.
     *
     * @param string $directory       Директория, с которой начать обход
     * @param string $componentName  Навазвание компонента
     */
    protected function replaceNamespacePlaceholders(string $directory, string $componentName): void
    {
        $namespaceValue = $this->getNamespace($componentName);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (strtolower($file->getExtension()) === 'php' && $file->isFile()) {
                $contents = file_get_contents($file->getPathname());
                $newContents = str_replace('[NAMESPACE]', $namespaceValue, $contents);

                if ($newContents !== $contents) { // Изменилась ли строка
                    file_put_contents($file->getPathname(), $newContents);
                }
            }
        }
    }

    /**
     * Рекурсивное удаление директории
     *
     * @param string $dir - Путь к директории
     * @return bool
     */
    protected function removeDirectory(string $dir) : bool
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            $this->removeDirectory($dir . DIRECTORY_SEPARATOR . $item);
        }
        return rmdir($dir);
    }

    /**
     * Проверка обновлений для компонента
     * @param string $componentName
     * @param string $currentVersion
     * @return array|null
     */
    public function checkForUpdates($componentName, $currentVersion)
    {
        try {
            $componentInfo = $this->getComponentInfo($componentName);
            if (!$componentInfo) {
                return null;
            }

            $params = [
                'component_id' => $componentInfo['id'],
                'current_version' => $currentVersion
            ];

            $response = $this->makeApiRequest('check-version', 'GET', $params);

            if ($response['status'] === 'success') {
                return $response['data'];
            }

            return null;

        } catch (\Exception $e) {
            Yii::error("Ошибка проверки обновлений для '$componentName': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Загрузка компонента (если существует)
     * @param string $componentName
     * @return mixed|null
     */
    public function loadComponent($componentName)
    {
        if (isset($this->componentsCache[$componentName])) {
            return $this->componentsCache[$componentName];
        }

        if (!$this->hasComponent($componentName)) {
            return null;
        }

        try {
            $componentPath = $this->getComponentPath($componentName);
            $componentFile = $componentPath . '/Component.php';

            if (file_exists($componentFile)) {
                require_once $componentFile;

                // Предполагаем, что класс компонента называется ComponentNameComponent
                $className = $this->getComponentClassName($componentName);

                if (class_exists($className)) {
                    $component = new $className();
                    $this->componentsCache[$componentName] = $component;
                    return $component;
                }
            }

        } catch (\Exception $e) {
            Yii::error("Ошибка загрузки компонента '$componentName': " . $e->getMessage());
        }

        return null;
    }

    /**
     * Получение информации о компоненте
     * @param string $alias
     * @return array|null
     */
    private function getComponentInfo($alias)
    {
        $components = $this->getAvailableComponents();

        foreach ($components as $component) {
            if ($component['alias'] === $alias) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Получение пути к компоненту
     * @param string $componentName
     * @return string
     */
    private function getComponentPath($componentName)
    {
        $basePath = Yii::getAlias($this->componentsPath);
        return $basePath . '/' . $componentName;
    }

    /**
     * Получение имени класса компонента
     * @param string $componentName
     * @return string
     */
    private function getComponentClassName($componentName)
    {
        // Преобразуем имя компонента в имя класса
        $className = str_replace([' ', '-', '_'], '', ucwords($componentName, ' -_'));
        return $className . 'Component';
    }

    /**
     * Извлечение архива
     * @param string $archivePath
     * @param string $destination
     * @throws \Exception
     */
    private function extractArchive(string $archivePath, string $destination) : void
    {
        if (!extension_loaded('zip')) {
            throw new \Exception('Расширение ZIP не установлено');
        }

        $zip = new ZipArchive();
        $result = $zip->open($archivePath);

        if ($result !== TRUE) {
            throw new \Exception("Ошибка открытия архива: $result");
        }

        // Создаем директорию назначения
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        // Извлекаем файлы
        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new \Exception('Ошибка извлечения архива');
        }

        $zip->close();
    }

    /**
     * Извлечение архива
     * @param string $zipFile
     * @param string $extractTo
     * @throws \Exception
     */
    protected function extractArchiveIncremental(string $zipFile, string $extractTo) : void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new \Exception("Не удалось открыть архив '$zipFile'");
        }

        // Создаем папку, если ее нет
        if (!is_dir($extractTo)) {
            mkdir($extractTo, 0755, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            // Определяем полный путь для извлечения файла
            $destPath = $extractTo . DIRECTORY_SEPARATOR . $entry;

            if (substr($destPath, -1) === DIRECTORY_SEPARATOR) {
                // Это папка - создаем, если не существует
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                // Файл - извлекаем и перезаписываем
                $dir = dirname($destPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // Копируем файл из архива в папку (перезаписывая существующий)
                $stream = $zip->getStream($entry);
                if (!$stream) {
                    throw new \Exception("Не удалось получить поток для файла '$entry' из архива");
                }

                $outFile = fopen($destPath, 'wb');
                if (!$outFile) {
                    fclose($stream);
                    throw new \Exception("Не удалось открыть файл для записи '$destPath'");
                }

                while (!feof($stream)) {
                    fwrite($outFile, fread($stream, 8192));
                }

                fclose($outFile);
                fclose($stream);
            }
        }

        $zip->close();
    }

    /**
     * Выполнение API запроса
     * @param string $endpoint
     * @param string $method
     * @param array $params
     * @return array
     * @throws \Exception
     */
    private function makeApiRequest($endpoint, $method = 'GET', $params = [])
    {
        $url = rtrim($this->apiUrl, '/') . '/mitisk/' . $endpoint;

        // Добавляем обязательные параметры
        $params['api_key'] = $this->apiKey;
        $params['site_url'] = $this->siteUrl;

        $options = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: ComponentHelper/1.0'
                ],
                'timeout' => 30
            ]
        ];

        if ($method === 'POST') {
            $options['http']['content'] = http_build_query($params);
        } else {
            $url .= '?' . http_build_query($params);
        }

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \Exception('Ошибка соединения с API сервером');
        }

        try {
            $decodedResponse = Json::decode($response);
        } catch(\yii\base\InvalidArgumentException $e) {
            // обрабатываем ошибку
            $decodedResponse = $response;
        }

        if (!$decodedResponse) {
            throw new \Exception('Некорректный ответ от API сервера');
        }

        return $decodedResponse;
    }

    /**
     * Получение установленных компонентов
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function getInstalledComponents() : array
    {
        return AdminComponent::find()->asArray()->all();
    }

    /**
     * Получение версии компонента
     * @param string $alias Алиас компонента
     * @return mixed|null
     */
    public function checkComponentVersion($alias, $version)
    {
        $components = self::getInstalledComponents();

        if ($components) {
            foreach ($components as $component) {
                if ($component['alias'] === $alias) {
                    return $component['version'] >= $version;
                }
            }
        }

        return null;
    }
}
