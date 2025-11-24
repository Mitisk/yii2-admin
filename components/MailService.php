<?php

namespace Mitisk\Yii2Admin\components;

use Mitisk\Yii2Admin\models\EmailTemplate;
use Yii;
use yii\base\Component;
use yii\symfonymailer\Mailer;

class MailService extends Component
{
    /**
     * Отправка письма по шаблону
     * * @param string $slug Уникальный код шаблона
     * @param string $to Email получателя
     * @param array $params Данные для подстановки ['username' => 'Ivan']
     * @return bool Успешность отправки
     */
    public function send(string $slug, string $to, array $params = []): bool
    {
        $template = EmailTemplate::findOne(['slug' => $slug, 'active' => 1]);

        if (!$template) {
            Yii::warning("Email template '$slug' not found or inactive.");
            return false;
        }

        // Проверка обязательных параметров
        $configParams = is_array($template->params) ? $template->params : [];
        foreach ($configParams as $key => $config) {
            // Если параметр обязателен (флаг required), но не передан или пуст
            if (isset($config['required']) && $config['required'] == 1) {
                if (!isset($params[$key]) || (string)$params[$key] === '') {
                    Yii::error("Missing required param '$key' for template '$slug'.");
                    return false;
                }
            }
        }

        // Замена плейсхолдеров
        $replacements = [];
        // Находим все возможные переменные в теле и теме
        // Сначала заполняем переданными данными
        foreach ($params as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }

        // Для тех, что не переданы - ставим пустоту, чтобы не светить {{var}} пользователю
        // Можно пройтись парсером, но проще добавить пустоту для известных конфигурируемых полей
        foreach ($configParams as $key => $config) {
            if (!isset($replacements['{{' . $key . '}}'])) {
                $replacements['{{' . $key . '}}'] = '';
            }
        }

        $subject = strtr($template->subject, $replacements);
        $body = strtr($template->body, $replacements);

        $layout = Yii::$app->settings->get('HIDDEN', 'mail_layout');

        // Проверяем, существует ли макет и есть ли в нем метка {{content}}
        if (!empty($layout) && strpos($layout, '{{content}}') !== false) {
            // Вставляем тело шаблона внутрь макета
            $finalBody = str_replace('{{content}}', $body, $layout);
        } else {
            // Если макета нет или он сломан, шлем как есть
            $finalBody = $body;
        }

        // Настройка mailer на лету
        $mailer = $this->configureMailer();

        try {
            return $mailer->compose()
                ->setHtmlBody($finalBody)
                ->setSubject($subject)
                ->setTo($to)
                ->setFrom([$this->getSetting('mailserver_login') => $this->getSetting('mailserver_from_name')])
                ->send();
        } catch (\Exception $e) {
            Yii::error("Email send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Создает и конфигурирует экземпляр мейлера на основе настроек из БД
     */
    protected function configureMailer()
    {
        // Получаем настройки через ваш компонент settings
        $host = $this->getSetting('mailserver_host');
        $port = (int)$this->getSetting('mailserver_port');
        $username = $this->getSetting('mailserver_login');
        $password = $this->getSetting('mailserver_password');

        // Определяем шифрование (обычно tls или ssl в зависимости от порта)
        $scheme = ($port === 465) ? 'smtps' : 'smtp';

        // Клонируем компонент мейлера приложения или создаем новый конфиг
        // Используем стандартную конфигурацию транспорта SwiftMailer/SymfonyMailer для Yii2

        $transport = [
            'scheme' => $scheme,
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'port' => $port,
        ];

        // Создаем новый экземпляр мейлера, чтобы не ломать глобальный конфиг, если он отличается
        // Важно: Предполагается использование yii\swiftmailer\Mailer или yii\symfonymailer\Mailer
        // Адаптируйте класс ниже под вашу версию Yii2 (Swift vs Symfony)

        $mailerConfig = [
            'class' => Mailer::class, // Или \yii\symfonymailer\Mailer::class
            'transport' => $transport,
            'useFileTransport' => false, // Реальная отправка
        ];

        return Yii::createObject($mailerConfig);
    }

    private function getSetting($key)
    {
        return Yii::$app->settings->get('Mitisk\Yii2Admin\models\MailTemplate', $key);
    }
}
