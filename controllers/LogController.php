<?php

namespace Mitisk\Yii2Admin\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;

/**
 * Class LogController
 * @package Mitisk\Yii2Admin\controllers
 */
class LogController extends Controller
{
    private string $logFile = '@runtime/logs/app.log';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['viewReports']
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $file = Yii::getAlias($this->logFile);
        $items = $this->tail($file, 200); // например, 200 строк
        return $this->render('index', ['items' => $items]);
    }

    private function tail(string $filepath, int $lines = 10): array
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return [];
        }
        $f = fopen($filepath, 'rb');
        if (!$f) return [];

        $buffer = '';
        $chunkSize = 4096;
        $pos = -1;
        $lineCount = 0;

        fseek($f, 0, SEEK_END);
        $filesize = ftell($f);

        while ($filesize > 0 && $lineCount <= $lines) {
            $seek = max($filesize - $chunkSize, 0);
            $read = $filesize - $seek;
            fseek($f, $seek);
            $buffer = fread($f, $read) . $buffer;
            $filesize = $seek;
            $lineCount = substr_count($buffer, "\n");
        }
        fclose($f);

        $rows = explode("\n", rtrim($buffer, "\n"));
        $rows = array_slice($rows, -$lines);
        // Приводим к короткому виду: [time][level][category] message
        return array_map(function ($line) {
            // Пример разбора стандартного формата FileTarget
            // 2025-10-18 20:12:33 [error] [application] Сообщение ...
            // Оставляем строку как есть или укоротим до 160 символов
            //$short = mb_strimwidth($line, 0, 160, '...');
            return trim($line);
        }, array_filter($rows, fn($v) => trim($v) !== ''));
    }
}
