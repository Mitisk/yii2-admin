<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\components\mail\MailEventInterface;
use Mitisk\Yii2Admin\components\mail\MailEvents;
use Mitisk\Yii2Admin\components\mail\TemplateParams;
use Mitisk\Yii2Admin\components\mail\TemplateRenderer;
use Mitisk\Yii2Admin\components\MailService;
use Mitisk\Yii2Admin\models\EmailTemplate;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\validators\EmailValidator;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Шаблоны писем (/admin/email-template/): список, редактор с предпросмотром и тестовой отправкой, общий макет.
 * Экран редактора — views/email-template/_form.php, assets/src/admin/email-template.js и .css (префикс et-).
 */
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
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'preview' => ['POST'],
                    'test-send' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $this->createMissingEventTemplates();

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
        $model = new EmailTemplate(['active' => 1]);

        if ($this->saveModel($model, null)) {
            return $this->redirect(['update', 'id' => $model->id]);
        }

        return $this->render('create', $this->formData($model, null));
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $event = MailEvents::fromModule()->find((string)$model->slug);

        if ($this->saveModel($model, $event)) {
            // Остаёмся на странице: после правки текста обычно смотрят предпросмотр и шлют тест
            return $this->redirect(['update', 'id' => $model->id]);
        }

        return $this->render('update', $this->formData($model, $event));
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Шаблон удалён');

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
     * Письмо так, как его увидит получатель: текст из редактора (ещё не сохранённый), значения-примеры
     * вместо переменных, общий макет.
     *
     * @return array{subject: string, html: string, hasLayout: bool}
     */
    public function actionPreview(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        [$subject, $body, $params] = $this->postedContent();

        $layout = $this->mailLayout();
        $email = (new TemplateRenderer())->render(
            $subject,
            $body,
            TemplateParams::samples($params, TemplateParams::names($subject, $body, (string)$layout)),
            TemplateParams::htmlNames($params),
            $layout,
        );

        return ['subject' => $email->subject, 'html' => $email->html, 'hasLayout' => $layout !== null];
    }

    /**
     * Тестовое письмо: то, что сейчас в редакторе, со значениями-примерами.
     *
     * @return array{success: bool, message: string}
     */
    public function actionTestSend(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $email = trim((string)Yii::$app->request->post('email', ''));
        if (!(new EmailValidator())->validate($email)) {
            return ['success' => false, 'message' => 'Укажите адрес, на который отправить письмо.'];
        }

        [$subject, $body, $params] = $this->postedContent();
        if (trim($subject) === '' && trim($body) === '') {
            return ['success' => false, 'message' => 'В письме пока нет ни темы, ни текста.'];
        }

        /** @var MailService $mailer */
        $mailer = Yii::createObject(MailService::class);
        if (!$mailer->isConfigured()) {
            return ['success' => false, 'message' => 'Почтовый сервер не настроен: укажите его в настройках писем (кнопка «Почтовый сервер»).'];
        }

        $sent = $mailer->sendContent(
            $email,
            'Тест: ' . $subject,
            $body,
            TemplateParams::samples($params, TemplateParams::names($subject, $body, (string)$this->mailLayout())),
            TemplateParams::htmlNames($params),
        );

        return $sent
            ? ['success' => true, 'message' => 'Письмо отправлено на ' . $email . '. Если его нет во «Входящих», проверьте «Спам».']
            : ['success' => false, 'message' => 'Письмо не ушло: почтовый сервер не принял его или недоступен. Подробности — в журнале ошибок сайта.'];
    }

    /**
     * Данные формы: переменные (из кода события, сохранённые и найденные в тексте), отправитель, тексты из кода.
     *
     * @return array<string, mixed>
     */
    private function formData(EmailTemplate $model, ?MailEventInterface $event): array
    {
        /** @var MailService $mailer */
        $mailer = Yii::createObject(MailService::class);
        $adminEmails = array_values(array_filter(array_map(
            'trim',
            explode(',', (string)Yii::$app->settings->get('GENERAL', 'admin_email', ''))
        )));

        return [
            'model' => $model,
            'event' => $event,
            'rows' => TemplateParams::rows(
                is_array($model->params) ? $model->params : [],
                $event !== null ? $event->params() : [],
                TemplateParams::names((string)$model->subject, (string)$model->body),
            ),
            'sender' => $mailer->sender(),
            'smtpReady' => $mailer->isConfigured(),
            'hasLayout' => $this->mailLayout() !== null,
            'adminEmails' => $adminEmails,
        ];
    }

    private function saveModel(EmailTemplate $model, ?MailEventInterface $event): bool
    {
        $post = Yii::$app->request->post();
        $slug = $model->slug;
        if (!$model->load($post)) {
            return false;
        }
        if ($event !== null) {
            // По этому коду письмо отправляет код проекта — из формы он не меняется
            $model->slug = $slug;
        }

        $used = TemplateParams::names((string)$model->subject, (string)$model->body);
        $rows = TemplateParams::rows(
            TemplateParams::normalize($post[$model->formName()]['params'] ?? []),
            $event !== null ? $event->params() : [],
            $used,
        );
        $params = [];
        foreach ($rows as $name => $row) {
            // Опечатка или убранная из текста переменная без описания и примера — не хранится
            if (!$row['used'] && !$row['code'] && $row['desc'] === '' && $row['sample'] === '') {
                continue;
            }
            $params[$name] = [
                'desc' => $row['desc'],
                'required' => $row['required'],
                'html' => $row['html'],
                'sample' => $row['sample'],
            ];
        }
        $model->params = $params;

        if ($model->save()) {
            Yii::$app->session->setFlash('success', 'Шаблон сохранён');

            return true;
        }

        return false;
    }

    /**
     * Тема, тело и переменные из запроса предпросмотра или тестовой отправки.
     *
     * @return array{0: string, 1: string, 2: array<string, array<string, string>>}
     */
    private function postedContent(): array
    {
        $request = Yii::$app->request;

        return [
            (string)$request->post('subject', ''),
            (string)$request->post('body', ''),
            TemplateParams::normalize($request->post('params', [])),
        ];
    }

    /** Общий макет писем — тот же, что подставляет MailService; не задан или без метки {{content}} — null. */
    private function mailLayout(): ?string
    {
        $layout = Yii::$app->settings->get('HIDDEN', 'mail_layout');

        return is_string($layout) && str_contains($layout, '{{content}}') ? $layout : null;
    }

    /** Письма-события из настройки mailEvents, которых ещё нет в списке, — как при первой отправке. */
    private function createMissingEventTemplates(): void
    {
        $events = MailEvents::fromModule()->all();
        if ($events === []) {
            return;
        }
        $existing = EmailTemplate::find()->select('slug')->where(['slug' => array_keys($events)])->column();
        $missing = array_diff_key($events, array_flip($existing));
        if ($missing === []) {
            return;
        }
        /** @var MailService $mailer */
        $mailer = Yii::createObject(MailService::class);
        foreach ($missing as $event) {
            $mailer->ensureTemplate($event);
        }
    }

    protected function findModel($id): EmailTemplate
    {
        if (($model = EmailTemplate::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Шаблон письма не найден.');
    }
}
