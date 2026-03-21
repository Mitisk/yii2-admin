<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\AuditLog;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

class LogController extends BaseController
{
    private string $logFile = '@runtime/logs/app.log';

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['viewReports'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'system-log' => ['GET'],
                    'clear-audit' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Страница лога с двумя вкладками.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $query = AuditLog::find()->with('user');

        // Фильтры
        $filterUser = Yii::$app->request->get('user_id');
        $filterAction = Yii::$app->request->get('action');
        $filterSearch = trim(
            (string)Yii::$app->request->get('search', '')
        );
        $filterDateFrom = Yii::$app->request->get('date_from');
        $filterDateTo = Yii::$app->request->get('date_to');

        if ($filterUser) {
            $query->andWhere(['user_id' => $filterUser]);
        }
        if ($filterAction) {
            $query->andWhere(['action' => $filterAction]);
        }
        if ($filterSearch !== '') {
            $query->andWhere(
                ['like', 'model_label', $filterSearch]
            );
        }
        if ($filterDateFrom) {
            $ts = strtotime($filterDateFrom);
            if ($ts) {
                $query->andWhere(['>=', 'created_at', $ts]);
            }
        }
        if ($filterDateTo) {
            $ts = strtotime($filterDateTo . ' 23:59:59');
            if ($ts) {
                $query->andWhere(['<=', 'created_at', $ts]);
            }
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 25,
            ],
        ]);

        // Список пользователей для фильтра
        $userIds = AuditLog::find()
            ->select('user_id')
            ->distinct()
            ->where(['not', ['user_id' => null]])
            ->column();
        $users = AdminUser::find()
            ->where(['id' => $userIds])
            ->all();
        $usersList = [];
        foreach ($users as $u) {
            $usersList[$u->id] = $u->name ?: $u->username;
        }

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'usersList' => $usersList,
            'filterUser' => $filterUser,
            'filterAction' => $filterAction,
            'filterSearch' => $filterSearch,
            'filterDateFrom' => $filterDateFrom,
            'filterDateTo' => $filterDateTo,
        ]);
    }

    /**
     * Очистка журнала аудита.
     *
     * @return \yii\web\Response
     */
    public function actionClearAudit(): \yii\web\Response
    {
        if (!Yii::$app->user->can('superAdminRole')) {
            Yii::$app->session->setFlash(
                'error',
                'Недостаточно прав для очистки журнала.'
            );
            return $this->redirect(['index']);
        }

        $count = AuditLog::find()->count();
        AuditLog::deleteAll();

        Yii::$app->session->setFlash(
            'success',
            "Журнал очищен. Удалено записей: {$count}."
        );

        return $this->redirect(['index']);
    }

    /**
     * AJAX-эндпоинт для системного лога.
     *
     * @return array
     */
    public function actionSystemLog(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $offset = (int)Yii::$app->request->get('offset', 0);
        $limit = (int)Yii::$app->request->get('limit', 100);
        $level = Yii::$app->request->get('level', '');
        $search = trim(
            (string)Yii::$app->request->get('search', '')
        );

        $file = Yii::getAlias($this->logFile);
        $allLines = $this->tail($file, 5000);
        $grouped = $this->groupLogEntries($allLines);

        $parsed = [];
        foreach ($grouped as $row) {
            if ($level !== '' && $row['level'] !== $level) {
                continue;
            }
            $haystack = $row['message'] . ' ' . $row['full'];
            if ($search !== ''
                && mb_stripos($haystack, $search) === false
            ) {
                continue;
            }
            $parsed[] = $row;
        }

        // Реверсируем: новые сверху
        $parsed = array_reverse($parsed);

        $total = count($parsed);
        $items = array_slice($parsed, $offset, $limit);

        return [
            'items' => $items,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total,
        ];
    }

    /**
     * Читает последние N строк файла.
     *
     * @param string $filepath Путь к файлу
     * @param int    $lines    Количество строк
     *
     * @return array
     */
    private function tail(string $filepath, int $lines = 10): array
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return [];
        }
        $f = fopen($filepath, 'rb');
        if (!$f) {
            return [];
        }

        $buffer = '';
        $chunkSize = 8192;
        fseek($f, 0, SEEK_END);
        $pos = ftell($f);

        $lineCount = 0;
        while ($pos > 0 && $lineCount <= $lines) {
            $read = min($chunkSize, $pos);
            $pos -= $read;
            fseek($f, $pos);
            $buffer = fread($f, $read) . $buffer;
            $lineCount = substr_count($buffer, "\n");
        }
        fclose($f);

        $rows = explode("\n", rtrim($buffer, "\n"));
        return array_slice($rows, -$lines);
    }

    /**
     * Группирует строки лога: основная + стектрейс.
     *
     * @param array $lines Сырые строки файла
     *
     * @return array Массив записей с полями
     *               datetime, ip, level, category, message, full
     */
    private function groupLogEntries(array $lines): array
    {
        $entries = [];
        $current = null;

        $dateRe = '/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+\[/';

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '') {
                // Пустая строка внутри записи — добавляем
                if ($current !== null) {
                    $current['_extra'][] = '';
                }
                continue;
            }

            if (preg_match($dateRe, $line)) {
                // Новая запись — сохраняем предыдущую
                if ($current !== null) {
                    $entries[] = $this->finalizeEntry($current);
                }
                $current = $this->parseHeaderLine($line);
            } else {
                // Строка стека/продолжения
                if ($current !== null) {
                    $current['_extra'][] = $line;
                }
            }
        }

        if ($current !== null) {
            $entries[] = $this->finalizeEntry($current);
        }

        return $entries;
    }

    /**
     * Парсит заголовочную строку лога (с датой).
     *
     * @param string $line Строка
     *
     * @return array
     */
    private function parseHeaderLine(string $line): array
    {
        $base = [
            'datetime' => '',
            'ip' => '-',
            'level' => 'info',
            'category' => '',
            'message' => $line,
            '_extra' => [],
        ];

        // 6 блоков: [ip][uid][sid][level][category]
        $re = '/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]\s*(.*)$/u';
        if (preg_match($re, $line, $m)) {
            $base['datetime'] = $m[1];
            $base['ip'] = $m[2] !== '' ? $m[2] : '-';
            $base['level'] = $m[5];
            $base['category'] = $m[6];
            $base['message'] = $m[7];
            return $base;
        }

        // 5 блоков
        $re2 = '/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]'
            . '\[([^\]]*)\]\s*(.*)$/u';
        if (preg_match($re2, $line, $m)) {
            $base['datetime'] = $m[1];
            $base['ip'] = $m[2] !== '' ? $m[2] : '-';
            $base['level'] = $m[5];
            $base['message'] = $m[6];
            return $base;
        }

        return $base;
    }

    /**
     * Формирует итоговую запись с полным текстом.
     *
     * @param array $entry Заготовка записи
     *
     * @return array
     */
    private function finalizeEntry(array $entry): array
    {
        $extra = $entry['_extra'];
        unset($entry['_extra']);

        $fullParts = [$entry['message']];
        foreach ($extra as $line) {
            $fullParts[] = $line;
        }
        $fullText = implode("\n", $fullParts);

        // Краткое сообщение — первая строка, обрезанная
        $firstLine = $entry['message'];
        $entry['message'] = mb_strimwidth(
            $firstLine,
            0,
            300,
            '...'
        );
        $entry['full'] = trim($fullText);

        return $entry;
    }
}
