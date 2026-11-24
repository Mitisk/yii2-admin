<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app;

use Yii;
use yii\web\AssetManager;

/**
 * Менеджер ассетов для тестов: отдаёт адреса, но ничего не копирует и не проверяет на диске
 * (папка ассетов пакета — десятки тысяч файлов вместе с node_modules, bower-пакетов может не быть).
 */
final class NullAssetManager extends AssetManager
{
    /**
     * @param string $path
     * @param array<string, mixed> $options
     * @return array{0: string, 1: string}
     */
    public function publish($path, $options = [])
    {
        $hash = $this->hash((string) Yii::getAlias($path));

        return [$this->basePath . DIRECTORY_SEPARATOR . $hash, $this->baseUrl . '/' . $hash];
    }

    /**
     * @param string $path
     */
    protected function hash($path)
    {
        return sprintf('%x', crc32($path));
    }
}
