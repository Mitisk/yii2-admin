<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\audit\AuditActions;
use Mitisk\Yii2Admin\components\audit\AuditPresenter;
use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\components\log\LogParser;
use Mitisk\Yii2Admin\components\log\SystemLog;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\AuditLog;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Журналы админки (/admin/log/): «Действия» — кто и что менял (admin_audit_log),
 * «Ошибки сайта» — лог приложения, собранный в проблемы.
 *
 * Действия видит право viewReports. Лог приложения — только роль admin: в нём стеки, пути
 * и данные запросов. Очистка журнала действий — superAdminRole.
 */
class LogController extends BaseController
{
    /** Быстрые периоды фильтра: ключ => [подпись, сколько суток назад начинается]. */
    public const PERIODS = [
        'today' => ['Сегодня', 0],
        '7' => ['7 дней', 6],
        '30' => ['30 дней', 29],
    ];

    /** Варианты очистки: за сколько дней оставить записи; 0 — удалить все. */
    public const KEEP_DAYS = [365, 180, 90, 30, 0];

    private const PAGE_SIZE = 50;
    private const LIST_LIMIT = 100;
    private const GROUPS_LIMIT = 200;

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
                        'actions' => ['system-log'],
                        'roles' => ['admin'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['index', 'clear-audit'],
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
     * Журнал действий; вкладку «Ошибки сайта» заполняет system-log.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;
        $filters = [
            'user_id' => (string)$request->get('user_id', ''),
            'action' => (string)$request->get('action', ''),
            'section' => (string)$request->get('section', ''),
            'period' => (string)$request->get('period', ''),
            'date_from' => (string)$request->get('date_from', ''),
            'date_to' => (string)$request->get('date_to', ''),
            'search' => trim((string)$request->get('search', '')),
        ];
        // Свои даты важнее быстрого периода: кнопки периода ведут на адрес без дат
        if (!isset(self::PERIODS[$filters['period']]) || $filters['date_from'] !== '' || $filters['date_to'] !== '') {
            $filters['period'] = '';
        }

        $query = AuditLog::find()->with('user');
        $this->applyFilters($query, $filters);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC, 'id' => SORT_DESC]],
            'pagination' => ['pageSize' => self::PAGE_SIZE],
        ]);

        $actions = AuditActions::fromModule();
        $presenter = new AuditPresenter();

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'filters' => $filters,
            'actions' => $actions,
            'presenter' => $presenter,
            'usersList' => $this->usersInLog(),
            'actionsList' => $actions->options($this->distinct('action')),
            'sectionsList' => $presenter->sections($this->distinct('model_class')),
            'totalAll' => (int)AuditLog::find()->count(),
            'oldest' => AuditLog::find()->min('created_at'),
            'canClear' => Yii::$app->user->can('superAdminRole'),
            'canSystem' => Yii::$app->user->can('admin'),
        ]);
    }

    /**
     * Удаляет записи журнала старше выбранного срока (0 — все) и пишет об этом в журнал.
     */
    public function actionClearAudit(): Response
    {
        if (!Yii::$app->user->can('superAdminRole')) {
            Yii::$app->session->setFlash('error', 'Недостаточно прав для очистки журнала.');

            return $this->redirect(['index']);
        }

        $days = (int)Yii::$app->request->post('keep_days', -1);
        if (!in_array($days, self::KEEP_DAYS, true)) {
            Yii::$app->session->setFlash('error', 'Выберите, за какой срок оставить записи.');

            return $this->redirect(['index']);
        }

        $count = (int)AuditLog::deleteAll($days > 0 ? ['<', 'created_at', time() - $days * 86400] : null);

        $what = $days > 0
            ? 'удалены записи старше ' . $days . ' дн.'
            : 'удалены все записи';
        if ($count > 0) {
            AuditService::event(
                AuditActions::CLEAR,
                AuditLog::class,
                'Журнал действий: ' . $what . ' — ' . $count . ' шт.'
            );
        }

        Yii::$app->session->setFlash(
            $count > 0 ? 'success' : 'info',
            $count > 0 ? 'Удалено записей: ' . $count . '.' : 'Таких старых записей в журнале нет.'
        );

        return $this->redirect(['index']);
    }

    /**
     * Лог приложения для вкладки «Ошибки сайта».
     *
     * view=groups — одинаковые записи одной строкой со счётчиком; view=list — подряд, по LIST_LIMIT.
     *
     * @return array<string, mixed>
     */
    public function actionSystemLog(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;
        $view = $request->get('view') === 'list' ? 'list' : 'groups';
        $level = (string)$request->get('level', '');
        $search = trim((string)$request->get('search', ''));
        $hideNoise = filter_var($request->get('hide_noise', '0'), FILTER_VALIDATE_BOOLEAN);
        $offset = max(0, (int)$request->get('offset', 0));

        $log = SystemLog::fromApp();
        $data = $log->read();
        $parser = new LogParser((string)Yii::getAlias('@app'));

        $entries = [];
        foreach ($data['chunks'] as $chunk) {
            foreach ($parser->parse($chunk) as $entry) {
                $entries[] = $entry;
            }
        }

        $dayStart = strtotime('today');
        $summary = [
            'errorsToday' => 0,
            'warningsToday' => 0,
            'noise' => 0,
            'total' => count($entries),
            'from' => $entries !== [] ? $this->when((int)$entries[0]['ts']) : '',
            'size' => $this->size($data['size']),
            'truncated' => $data['truncated'],
            'empty' => $data['size'] === 0,
        ];

        $filtered = [];
        foreach ($entries as $entry) {
            if ($entry['noise']) {
                $summary['noise']++;
            } elseif ($entry['ts'] >= $dayStart) {
                if ($entry['level'] === 'error') {
                    $summary['errorsToday']++;
                } elseif ($entry['level'] === 'warning') {
                    $summary['warningsToday']++;
                }
            }
            if ($hideNoise && $entry['noise']) {
                continue;
            }
            if ($level !== '' && $entry['level'] !== $level) {
                continue;
            }
            if ($search !== '') {
                $haystack = $entry['title'] . ' ' . $entry['class'] . ' ' . $entry['category'] . ' '
                    . $entry['url'] . ' ' . $entry['full'];
                if (mb_stripos($haystack, $search) === false) {
                    continue;
                }
            }
            $filtered[] = $entry;
        }

        if ($view === 'groups') {
            $groups = $parser->groups($filtered);
            $total = count($groups);
            $items = array_slice($groups, 0, self::GROUPS_LIMIT);
            $hasMore = false;
        } else {
            $total = count($filtered);
            $items = array_slice(array_reverse($filtered), $offset, self::LIST_LIMIT);
            $hasMore = $offset + self::LIST_LIMIT < $total;
        }

        return [
            'view' => $view,
            'summary' => $summary,
            'total' => $total,
            'matched' => count($filtered),
            'hasMore' => $hasMore,
            'items' => $this->present($items, $view),
        ];
    }

    /**
     * @param \yii\db\ActiveQuery $query
     * @param array<string, string> $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if ($filters['user_id'] === 'system') {
            $query->andWhere(['user_id' => null]);
        } elseif (ctype_digit($filters['user_id'])) {
            $query->andWhere(['user_id' => (int)$filters['user_id']]);
        }
        if ($filters['action'] !== '') {
            $query->andWhere(['action' => $filters['action']]);
        }
        if ($filters['section'] !== '') {
            $query->andWhere(['model_class' => $filters['section']]);
        }
        if ($filters['search'] !== '') {
            // Название записи, её номер и сами изменения: «кто менял телефон на …»
            $jsonNeedle = trim((string)json_encode($filters['search'], JSON_UNESCAPED_UNICODE), '"');
            $query->andWhere([
                'or',
                ['like', 'model_label', $filters['search']],
                ['model_id' => $filters['search']],
                ['like', 'diff', $jsonNeedle],
            ]);
        }

        if ($filters['period'] !== '') {
            $query->andWhere(['>=', 'created_at', strtotime('today') - self::PERIODS[$filters['period']][1] * 86400]);

            return;
        }
        $from = $filters['date_from'] !== '' ? strtotime($filters['date_from']) : false;
        if ($from !== false) {
            $query->andWhere(['>=', 'created_at', $from]);
        }
        $to = $filters['date_to'] !== '' ? strtotime($filters['date_to'] . ' 23:59:59') : false;
        if ($to !== false) {
            $query->andWhere(['<=', 'created_at', $to]);
        }
    }

    /**
     * @return string[]
     */
    private function distinct(string $column): array
    {
        return array_map('strval', AuditLog::find()->select($column)->distinct()->column());
    }

    /**
     * @return array<string, string> id => имя; «system» — записи без пользователя
     */
    private function usersInLog(): array
    {
        $ids = AuditLog::find()->select('user_id')->distinct()->column();
        $list = [];
        if (in_array(null, $ids, true)) {
            $list['system'] = 'Система';
        }
        $names = $this->userNames(array_filter($ids, static fn ($id): bool => $id !== null));
        asort($names, SORT_LOCALE_STRING);

        return $list + $names;
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<string, string>
     */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $names = [];
        $rows = (new Query())->select(['id', 'name', 'username'])->from(AdminUser::tableName())->where(['id' => $ids])->all();
        foreach ($rows as $row) {
            $names[(string)$row['id']] = trim((string)($row['name'] ?: $row['username']));
        }

        return $names;
    }

    /**
     * Записи и группы лога для экрана: подписи времени и имена пользователей.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function present(array $items, string $view): array
    {
        $userIds = [];
        foreach ($items as $item) {
            foreach ($view === 'groups' ? $item['samples'] : [$item] as $case) {
                if (ctype_digit((string)$case['user'])) {
                    $userIds[] = (int)$case['user'];
                }
            }
        }
        $names = $this->userNames($userIds);

        $case = function (array $row) use ($names): array {
            $user = (string)$row['user'];

            return [
                'when' => $this->when((int)$row['ts'], true),
                'ip' => (string)$row['ip'],
                'user' => $user,
                'userName' => $names[$user] ?? '',
                'url' => (string)$row['url'],
                'method' => (string)$row['method'],
            ];
        };

        $result = [];
        foreach ($items as $item) {
            $row = [
                'level' => $item['level'],
                'category' => $item['category'],
                'code' => $item['code'],
                'noise' => $item['noise'],
                'title' => $item['title'],
                'class' => $item['class'],
                'where' => str_replace('\\', '/', (string)$item['where']),
                'full' => $item['full'],
            ];
            if ($view === 'groups') {
                $row += [
                    'id' => $item['id'],
                    'count' => $item['count'],
                    'today' => $item['today'],
                    'last' => $this->when((int)$item['last']),
                    'first' => $this->when((int)$item['first']),
                    'cases' => array_map($case, $item['samples']),
                ];
            } else {
                $row += $case($item);
            }
            $result[] = $row;
        }

        return $result;
    }

    /** «340 КБ», «30,3 МБ». */
    private function size(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return max(1, (int)round($bytes / 1024)) . ' КБ';
        }

        return str_replace('.', ',', (string)round($bytes / 1024 / 1024, 1)) . ' МБ';
    }

    /**
     * «сегодня 14:36», «вчера 09:02», «24.11 11:02», «24.11.2025 11:02».
     */
    private function when(int $ts, bool $seconds = false): string
    {
        $time = date($seconds ? 'H:i:s' : 'H:i', $ts);
        $today = strtotime('today');
        if ($ts >= $today) {
            return 'сегодня ' . $time;
        }
        if ($ts >= $today - 86400) {
            return 'вчера ' . $time;
        }

        return date(date('Y', $ts) === date('Y') ? 'd.m' : 'd.m.Y', $ts) . ' ' . $time;
    }
}
