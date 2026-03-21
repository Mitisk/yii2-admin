<?php
declare(strict_types=1);

namespace Mitisk\Yii2Admin\components;

use Yii;
use yii\base\Component;
use yii\db\Query;
use yii\helpers\Url;

/**
 * SeoManager
 * 
 * Компонент динамического SEO на основе регулярных характеристик (паттернов).
 */
class SeoManager extends Component
{
    /**
     * @var array Массив для хранения переменных подстановки
     */
    protected array $context = [];

    /**
     * Установка данных для подстановки из контроллера
     * 
     * @param array $data Ключ-значение, например ['title' => 'Значение', '{count}' => 5]
     */
    public function setContext(array $data): void
    {
        foreach ($data as $key => $value) {
            // Гарантируем формат {var_name}
            $formattedKey = '{' . trim($key, '{}') . '}';
            $this->context[$formattedKey] = $value;
        }
    }

    /**
     * Метод для эффективной замены плейсхолдеров
     * 
     * @param string|null $template Исходная строка с плейсхолдерами
     * @return string|null Строка с замененными значениями или исходная строка
     */
    public function parse(?string $template): ?string
    {
        if (empty($template) || empty($this->context)) {
            return $template;
        }

        // strtr работает быстрее, чем множественный str_replace
        return strtr($template, $this->context);
    }

    /**
     * Регистрация мета-тегов в представлении на основе текущего URL
     */
    public function register(): void
    {
        $view = Yii::$app->getView();
        $url = Yii::$app->request->url; // URL включает path_info и query string (например: /news?id=5)

        // Получаем все активные правила, отсортированные по приоритету
        // В продакшене рекомендуется реализовать кэширование этого запроса
        $rules = (new Query())
            ->from('{{%seo_rules}}')
            ->where(['is_active' => true])
            ->orderBy(['priority' => SORT_DESC])
            ->all();

        $matchedRule = null;
        
        foreach ($rules as $rule) {
            $pattern = trim($rule['pattern']);
            if (empty($pattern)) {
                continue;
            }

            // Добавляем разделители regex, если их нет (упрощение ввода для админа)
            $delimiter = mb_substr($pattern, 0, 1);
            if (!in_array($delimiter, ['/', '#', '~', '@'])) {
                // Если нет разделителя, экранируем и оборачиваем
                $pattern = '#' . str_replace('#', '\#', $pattern) . '#iu';
            }

            // Проверяем совпадение паттерна с текущим URL
            // Используем @ для подавления ошибок при некорректном синтаксисе в БД
            if (@preg_match($pattern, $url) === 1) { 
                $matchedRule = $rule;
                break;
            }
        }

        if (!$matchedRule) {
            return; // Правило для данного URL не найдено
        }

        // Парсинг динамических переменных
        $title = $this->parse($matchedRule['title']);
        $description = $this->parse($matchedRule['description']);
        $keywords = $this->parse($matchedRule['keywords']);
        $ogTitle = $this->parse($matchedRule['og_title']);
        $ogDescription = $this->parse($matchedRule['og_description']);
        $ogImage = $this->parse($matchedRule['og_image']);
        $robots = $this->parse($matchedRule['robots']);

        // Регистрация тегов в \yii\web\View
        if (!empty($title)) {
            $view->title = $title;
        }

        if (!empty($description)) {
            $view->registerMetaTag(['name' => 'description', 'content' => $description], 'description');
        }

        if (!empty($keywords)) {
            $view->registerMetaTag(['name' => 'keywords', 'content' => $keywords], 'keywords');
        }

        if (!empty($robots)) {
            $view->registerMetaTag(['name' => 'robots', 'content' => $robots], 'robots');
        }

        // Open Graph теги (базовые)
        try {
            $view->registerMetaTag(['property' => 'og:url', 'content' => Url::canonical()], 'og:url');
        } catch (\Exception $e) {
            // Игнорируем ошибку, если Url::canonical() не может сгенерироваться
        }
        
        if (!empty($ogTitle)) {
            $view->registerMetaTag(['property' => 'og:title', 'content' => $ogTitle], 'og:title');
        } elseif (!empty($title)) {
            // Фолбэк на обычный тайтл, если OG Title не задан
            $view->registerMetaTag(['property' => 'og:title', 'content' => $title], 'og:title');
        }

        if (!empty($ogDescription)) {
            $view->registerMetaTag(['property' => 'og:description', 'content' => $ogDescription], 'og:description');
        } elseif (!empty($description)) {
            // Фолбэк на обычный дескрипшн
            $view->registerMetaTag(['property' => 'og:description', 'content' => $description], 'og:description');
        }

        if (!empty($ogImage)) {
            $view->registerMetaTag(['property' => 'og:image', 'content' => $ogImage], 'og:image');
        }
    }
}
