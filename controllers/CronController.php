<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\components\cron\CodeTasks;
use Mitisk\Yii2Admin\components\cron\CronExpression;
use Mitisk\Yii2Admin\components\cron\CronRunner;
use Mitisk\Yii2Admin\models\CronLog;
use Mitisk\Yii2Admin\models\CronTask;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Планировщик задач (/admin/cron/): консольные команды проекта по расписанию, журнал запусков.
 * Только для супер-администратора: задача — это запуск кода проекта.
 */
class CronController extends BaseController
{
    private const CODE_TASK_MESSAGE = 'Задача задана в коде проекта: расписание, команду и включение меняют в коде и выкладкой. Отсюда её можно только запустить вручную.';

    /** Сколько последних запусков показывать на странице задачи */
    private const LOG_ON_PAGE = 50;

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['superAdminRole']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'toggle' => ['POST'],
                    'run-now' => ['POST'],
                    'delete' => ['POST'],
                    'clear-log' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $now = time();
        $heartbeat = CronRunner::heartbeat();
        // Задачи из кода видны сразу, ещё до первого запуска диспетчера
        $codeErrors = CodeTasks::sync();
        $tasks = CronTask::find()
            ->orderBy([new \yii\db\Expression('[[code_key]] IS NULL'), 'enabled' => SORT_DESC, 'name' => SORT_ASC])
            ->all();

        $next = [];
        foreach ($tasks as $task) {
            $next[$task->id] = $task->enabled ? CronExpression::nextRun((string) $task->schedule, $now) : null;
        }

        return $this->render('index', [
            'tasks' => $tasks,
            'next' => $next,
            'heartbeat' => $heartbeat,
            'alive' => $heartbeat !== null && $now - $heartbeat <= CronRunner::SILENCE_AFTER,
            'crontabLine' => '* * * * * cd ' . Yii::getAlias('@app') . ' && php yii admin/cron/run >/dev/null 2>&1',
            'codeErrors' => $codeErrors,
            'failedToday' => (int) CronLog::find()->where(['<>', 'status', CronTask::STATUS_OK])->andWhere(['>=', 'started_at', $now - 86400])->count(),
        ]);
    }

    public function actionCreate()
    {
        $task = new CronTask(['enabled' => 1, 'timeout' => 600, 'schedule' => '0 3 * * *']);
        if ($task->load(Yii::$app->request->post()) && $task->save()) {
            Yii::$app->session->setFlash('success', 'Задача «' . $task->name . '» добавлена.');
            return $this->redirect(['index']);
        }

        return $this->render('form', ['task' => $task, 'logs' => []]);
    }

    public function actionUpdate(int $id)
    {
        $task = $this->findTask($id);
        if ($task->isFromCode()) {
            if (Yii::$app->request->isPost) {
                Yii::$app->session->setFlash('warning', self::CODE_TASK_MESSAGE);
                return $this->redirect(['update', 'id' => $task->id]);
            }
        } elseif ($task->load(Yii::$app->request->post()) && $task->save()) {
            Yii::$app->session->setFlash('success', 'Задача «' . $task->name . '» сохранена.');
            return $this->redirect(['index']);
        }

        return $this->render('form', [
            'task' => $task,
            'logs' => $task->getLogs()->limit(self::LOG_ON_PAGE)->all(),
        ]);
    }

    public function actionToggle(int $id): Response
    {
        $task = $this->findEditableTask($id);
        $task->updateAttributes(['enabled' => $task->enabled ? 0 : 1]);
        Yii::$app->session->setFlash('success', 'Задача «' . $task->name . '» ' . ($task->enabled ? 'включена' : 'выключена') . '.');

        return $this->redirect(['index']);
    }

    public function actionRunNow(int $id): Response
    {
        $task = $this->findTask($id);
        $task->requestRun();
        $alive = CronRunner::heartbeat() !== null && time() - (int) CronRunner::heartbeat() <= CronRunner::SILENCE_AFTER;
        Yii::$app->session->setFlash(
            $alive ? 'success' : 'warning',
            $alive
                ? 'Задача «' . $task->name . '» запустится в течение минуты. Результат появится в её журнале.'
                : 'Задача «' . $task->name . '» поставлена в очередь, но диспетчер не работает: подключите его в crontab сервера.'
        );

        return $this->redirect(Yii::$app->request->post('back') === 'form' ? ['update', 'id' => $task->id] : ['index']);
    }

    public function actionClearLog(int $id): Response
    {
        $task = $this->findTask($id);
        $removed = CronLog::deleteAll(['task_id' => $task->id]);
        Yii::$app->session->setFlash('success', 'Журнал задачи очищен, записей удалено: ' . $removed . '.');

        return $this->redirect(['update', 'id' => $task->id]);
    }

    public function actionDelete(int $id): Response
    {
        $task = $this->findEditableTask($id);
        $task->delete();
        Yii::$app->session->setFlash('success', 'Задача «' . $task->name . '» удалена вместе с журналом.');

        return $this->redirect(['index']);
    }

    /**
     * Задача, которую можно менять из админки: задачи из кода проекта выключают и удаляют в коде.
     */
    private function findEditableTask(int $id): CronTask
    {
        $task = $this->findTask($id);
        if ($task->isFromCode()) {
            throw new ForbiddenHttpException(self::CODE_TASK_MESSAGE);
        }

        return $task;
    }

    private function findTask(int $id): CronTask
    {
        $task = CronTask::findOne($id);
        if ($task === null) {
            throw new NotFoundHttpException('Задача не найдена.');
        }

        return $task;
    }
}
