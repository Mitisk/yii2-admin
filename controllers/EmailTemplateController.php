<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\models\EmailTemplate;
use Mitisk\Yii2Admin\components\MailService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class EmailTemplateController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['superAdminRole', 'admin'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => EmailTemplate::find(),
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC]],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $model = new EmailTemplate();

        if ($this->saveModel($model)) {
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->saveModel($model)) {
            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Шаблон удален');
        return $this->redirect(['index']);
    }

    /**
     * Редактирование общего макета писем (Layout)
     */
    public function actionLayout()
    {
        $section = 'HIDDEN';
        $key = 'mail_layout';

        // Обработка сохранения
        if (Yii::$app->request->isPost) {
            $content = Yii::$app->request->post('content');

            // Сохраняем настройку
            // Сигнатура: set($section, $key, $value, $type = 'string')
            if (Yii::$app->settings->set($section, $key, $content, 'html')) {
                Yii::$app->session->setFlash('success', 'Макет успешно сохранен');
                return $this->refresh();
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка сохранения макета');
            }
        }

        // Получаем текущее значение или дефолтное, если пусто
        $content = Yii::$app->settings->get($section, $key);

        if (empty($content)) {
            $year = date('Y');
            $siteName = \Yii::$app->settings->get('GENERAL', 'site_name');
            // Дефолтная рыба, чтобы было понятно, как пользоваться
            $content = <<<HTML
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                        .container { background: #fff; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; }
                        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        {{content}}
                    </div>
                    <div class="footer">
                        &copy; $year - $siteName
                    </div>
                </body>
                </html>
                HTML;
        }

        return $this->render('layout', [
            'content' => $content,
        ]);
    }

    /**
     * AJAX Action для тестовой отправки
     */
    public function actionTestSend()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        $id = $request->post('id');
        $email = $request->post('email');

        // Собираем значения переменных из формы, если нужно тестировать с данными
        // В упрощенном варианте шлем пустые или заглушки "Test Value"

        if (!$id || !$email) {
            return ['success' => false, 'message' => 'Некорректные данные'];
        }

        $model = $this->findModel($id);

        // Генерируем тестовые данные для обязательных параметров
        $params = [];
        if (is_array($model->params)) {
            foreach ($model->params as $key => $config) {
                $params[$key] = 'TEST_' . strtoupper($key);
            }
        }

        /** @var MailService $mailer */
        $mailer = Yii::createObject(MailService::class);

        if ($mailer->send($model->slug, $email, $params)) {
            return ['success' => true, 'message' => 'Тестовое письмо отправлено на ' . $email];
        }

        return ['success' => false, 'message' => 'Ошибка отправки. Проверьте настройки SMTP.'];
    }

    protected function saveModel(EmailTemplate $model): bool
    {
        if ($model->load(Yii::$app->request->post())) {
            // Обработка params приходит как массив из формы
            // Yii автоматически загрузит их в params если в форме имена вида EmailTemplate[params][key]
            // Но лучше убедиться, что это массив
            if (!is_array($model->params)) {
                $model->params = [];
            }
            // Убираем пустые ключи если вдруг попали

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Сохранено');
                return true;
            }
        }
        return false;
    }

    protected function findModel($id): EmailTemplate
    {
        if (($model = EmailTemplate::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}