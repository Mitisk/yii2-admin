<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Waf\SettingsException;
use Mitisk\Waf\WafControl;
use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\components\WafBridge;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Раздел «Защита»: управление пакетом mitisk/yii2-lightweight-waf. Без пакета — 404.
 */
class WafController extends BaseController
{
    private const LIST_LIMIT = 300;
    /** Блокировок на странице списка (WAF 2.2+; раньше показывались первые LIST_LIMIT) */
    private const BLOCKS_PER_PAGE = 50;

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['admin']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'save' => ['POST'],
                    'reset' => ['POST'],
                    'block' => ['POST'],
                    'unblock' => ['POST'],
                    'unblock-all' => ['POST'],
                    'allow-ip' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!WafBridge::isInstalled()) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        return true;
    }

    /**
     * Обзор, настройки, заблокированные адреса и журнал.
     */
    public function actionIndex(): string
    {
        $this->view->title = 'Защита от сканеров';
        $this->view->params['breadcrumbs'][] = 'Защита';

        if (!WafBridge::isSupported()) {
            return $this->render('outdated');
        }

        $control = WafBridge::control();
        $config = WafBridge::config();
        $checkUri = trim((string)Yii::$app->request->get('check', ''));

        // WAF 2.2+: счётчик без чтения файлов и список по страницам; старее — первые LIST_LIMIT записей
        $bulk = WafControl::API >= 2;
        $blocksPage = 1;
        $blocksPages = 1;
        if ($bulk) {
            $blocksTotal = $control->blocksCount();
            $blocksPages = max(1, (int)ceil($blocksTotal / self::BLOCKS_PER_PAGE));
            $blocksPage = min($blocksPages, max(1, (int)Yii::$app->request->get('blocksPage', 1)));
            $blocks = $control->blocksPage(self::BLOCKS_PER_PAGE, ($blocksPage - 1) * self::BLOCKS_PER_PAGE);
        } else {
            $all = $control->blocks();
            $blocksTotal = count($all);
            $blocks = array_slice($all, 0, self::LIST_LIMIT);
        }

        return $this->render('index', [
            'bulk' => $bulk,
            'blocksPage' => $blocksPage,
            'blocksPages' => $blocksPages,
            'config' => $config,
            'saved' => $control->settings(),
            'running' => WafBridge::isRunning(),
            'storageDir' => $control->storageDir(),
            'blocks' => $blocks,
            'blocksTotal' => $blocksTotal,
            'events' => $control->events(self::LIST_LIMIT),
            'stats' => $control->stats(7),
            'myIp' => WafBridge::clientIp(),
            'untrustedProxy' => WafBridge::untrustedProxy(),
            'checkUri' => $checkUri,
            'checkPattern' => $checkUri === '' ? null : $control->check($checkUri, [
                'badPatterns' => $config->basePatterns,
                'matchQuery' => $config->matchQuery,
            ]),
        ]);
    }

    /**
     * Сохранение настроек из формы.
     */
    public function actionSave(): Response
    {
        $form = (array)Yii::$app->request->post('Waf', []);

        try {
            WafBridge::save($this->settingsFromForm($form), $this->safePaths());
            Yii::$app->session->setFlash('success', 'Настройки защиты сохранены. Они действуют со следующего запроса.');
        } catch (SettingsException $e) {
            Yii::$app->session->setFlash('error', 'Настройки не сохранены. ' . implode(' ', $e->errors));
        } catch (\Throwable $e) {
            Yii::error('WAF: настройки не сохранены: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Настройки не сохранены: папка хранилища WAF недоступна для записи.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Вернуться к конфигу из web/index.php.
     */
    public function actionReset(): Response
    {
        WafBridge::reset();
        Yii::$app->session->setFlash('success', 'Настройки из админки убраны: действует конфиг из web/index.php.');

        return $this->redirect(['index']);
    }

    /**
     * Блокировка адреса вручную. 0 часов — бессрочно.
     */
    public function actionBlock(): Response
    {
        $request = Yii::$app->request;
        $ip = trim((string)$request->post('ip', ''));
        $hours = (float)str_replace(',', '.', (string)$request->post('hours', '24'));

        try {
            if ($ip === WafBridge::clientIp()) {
                throw new \InvalidArgumentException('Это ваш собственный адрес.');
            }
            WafBridge::control()->block($ip, $hours <= 0 ? null : (int)round($hours * 3600), trim((string)$request->post('note', '')));
            Yii::$app->session->setFlash('success', 'Адрес ' . $ip . ' заблокирован.');
        } catch (\InvalidArgumentException $e) {
            Yii::$app->session->setFlash('error', 'Адрес не заблокирован. ' . $e->getMessage());
        } catch (\Throwable $e) {
            Yii::error('WAF: блокировка не записана: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Адрес не заблокирован: папка хранилища WAF недоступна для записи.');
        }

        return $this->redirect(['index', '#' => 'blocks']);
    }

    /**
     * Снятие блокировки по адресу или ключу сети.
     */
    public function actionUnblock(): Response
    {
        $key = trim((string)Yii::$app->request->post('key', ''));
        if ($key !== '' && WafBridge::control()->unblock($key)) {
            Yii::$app->session->setFlash('success', 'Блокировка ' . $key . ' снята.');
        } else {
            Yii::$app->session->setFlash('error', 'Такой блокировки уже нет.');
        }

        return $this->redirect(['index', '#' => 'blocks']);
    }

    /**
     * Добавляет адрес в белый список и снимает с него блокировку.
     */
    public function actionAllowIp(): Response
    {
        $ip = trim((string)Yii::$app->request->post('ip', ''));
        $control = WafBridge::control();
        $settings = $control->settings();
        $settings['allowIps'] = array_values(array_unique(array_merge($settings['allowIps'] ?? [], [$ip])));

        try {
            WafBridge::save($settings, $this->safePaths());
            $control->unblock($ip);
            Yii::$app->session->setFlash('success', 'Адрес ' . $ip . ' добавлен в белый список.');
        } catch (SettingsException $e) {
            Yii::$app->session->setFlash('error', 'Адрес не добавлен. ' . implode(' ', $e->errors));
        } catch (\Throwable $e) {
            Yii::error('WAF: белый список не записан: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Адрес не добавлен: папка хранилища WAF недоступна для записи.');
        }

        return $this->redirect(['index']);
    }

    /**
     * Снять все блокировки разом (WAF 2.2+). Поставленные вручную остаются, если не отмечено «и ручные».
     */
    public function actionUnblockAll(): Response
    {
        if (!WafBridge::isSupported() || WafControl::API < 2) {
            Yii::$app->session->setFlash('error', 'Обновите mitisk/yii2-lightweight-waf до версии 2.2: в установленной снять все блокировки разом нельзя.');
            return $this->redirect(['index', '#' => 'blocks']);
        }

        $withManual = filter_var(Yii::$app->request->post('manual'), FILTER_VALIDATE_BOOLEAN);
        $removed = WafBridge::control()->unblockAll(!$withManual);
        Yii::$app->session->setFlash('success', 'Снято блокировок: ' . $removed . ($withManual ? '.' : '. Поставленные вручную оставлены.'));

        return $this->redirect(['index', '#' => 'blocks']);
    }

    /**
     * Поля формы → настройки WAF: часы и минуты в секунды, отмеченные шаблоны — в список выключенных.
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function settingsFromForm(array $form): array
    {
        $settings = [];
        foreach (['enabled', 'observeOnly', 'permanentBlock', 'skipCrossSite', 'verifyBots'] as $key) {
            if (array_key_exists($key, $form)) {
                $settings[$key] = $form[$key];
            }
        }
        foreach (['extraPatterns', 'allowIps', 'recaptchaSiteKey', 'recaptchaSecret', 'captchaMaxAttempts'] as $key) {
            if (array_key_exists($key, $form)) {
                $settings[$key] = is_string($form[$key]) ? $form[$key] : '';
            }
        }
        if (array_key_exists('maxBlocks', $form)) {
            $settings['maxBlocks'] = is_scalar($form['maxBlocks']) ? trim((string)$form['maxBlocks']) : '';
        }
        if (array_key_exists('blockHours', $form)) {
            $settings['blockDuration'] = (int)round((float)str_replace(',', '.', (string)$form['blockHours']) * 3600);
        }
        if (array_key_exists('captchaWindowMinutes', $form)) {
            $settings['captchaWindow'] = (int)round((float)str_replace(',', '.', (string)$form['captchaWindowMinutes']) * 60);
        }
        if (array_key_exists('basePatterns', $form)) {
            $base = array_filter((array)$form['basePatterns'], 'is_string');
            $enabled = array_filter((array)($form['enabledPatterns'] ?? []), 'is_string');
            $settings['disabledPatterns'] = array_values(array_diff($base, $enabled));
        }

        return $settings;
    }

    /**
     * Пути, которые свой шаблон не должен блокировать: главная и сама админка.
     *
     * @return string[]
     */
    private function safePaths(): array
    {
        return ['/', Url::to(['/admin/default/index']), Url::to(['/admin/default/login']), Url::to(['/admin/waf/index'])];
    }
}
