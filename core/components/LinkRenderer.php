<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\core\components;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * Рендерер пользовательской ссылки-кнопки.
 *
 * Делает подстановку алиасов вида `{attr}` из атрибутов модели,
 * применяет whitelist иконок/цветов/таргетов и экранирует вывод.
 */
final class LinkRenderer
{
    /**
     * Отрисовать ссылку из её конфигурации для конкретной записи.
     *
     * @param array $link Конфигурация ссылки (id, title, icon, color, url, target).
     * @param object|null $model Модель, атрибуты которой подставляются в URL.
     * @param string $context 'list' | 'view' | 'form' — влияет на классы размера.
     */
    public static function render(array $link, ?object $model = null, string $context = 'list'): string
    {
        $url    = self::substitute((string)($link['url'] ?? ''), $model);
        $title  = (string)($link['title'] ?? '');
        $icon   = (string)($link['icon'] ?? '');
        $color  = (string)($link['color'] ?? '');
        $target = (string)($link['target'] ?? '_self');

        if (!LinkPalette::isValidIcon($icon)) {
            $icon = '';
        }
        if (!LinkPalette::isValidColor($color)) {
            $color = '';
        }
        if (!LinkPalette::isValidTarget($target)) {
            $target = '_self';
        }

        $classes = ['admin-link-btn'];
        if ($color !== '') {
            $classes[] = $color;
        }
        if ($context === 'list') {
            $classes[] = 'admin-link-btn--sm';
        }

        $inner = '';
        if ($icon !== '') {
            $inner .= '<i class="' . Html::encode($icon) . '"></i>';
        }
        if ($title !== '') {
            if ($inner !== '') {
                $inner .= ' ';
            }
            $inner .= '<span>' . Html::encode($title) . '</span>';
        }
        if ($inner === '') {
            $inner = '<i class="icon-link"></i>';
        }

        $options = [
            'class' => implode(' ', $classes),
            'title' => $title !== '' ? $title : null,
        ];

        if ($target === 'ajax') {
            $options['data-ajax-url']   = $url;
            $options['data-ajax-csrf']  = Yii::$app->request->getCsrfToken();
            $options['class']          .= ' js-admin-link-ajax';
            $url                        = '#';
        } elseif ($target === '_blank') {
            $options['target'] = '_blank';
            $options['rel']    = 'noopener noreferrer';
        }

        $options['href'] = $url;

        return Html::tag('a', $inner, $options);
    }

    /**
     * Подставляет `{attr}` значения из модели в строку URL.
     * Неизвестные атрибуты заменяются пустой строкой.
     */
    public static function substitute(string $url, ?object $model): string
    {
        if ($url === '' || $model === null) {
            return $url;
        }

        return (string)preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use ($model): string {
                $name = $m[1];
                $value = null;
                if ($model instanceof ActiveRecord && $model->hasAttribute($name)) {
                    $value = $model->getAttribute($name);
                } elseif ($model instanceof Model && $model->canGetProperty($name)) {
                    $value = $model->$name;
                } elseif (is_object($model) && isset($model->$name)) {
                    $value = $model->$name;
                } else {
                    Yii::warning(
                        "LinkRenderer: атрибут '{$name}' не найден у модели",
                        __METHOD__
                    );
                    return '';
                }
                return rawurlencode((string)$value);
            },
            $url
        );
    }

    /**
     * Находит ссылку в пуле по её uid.
     *
     * @param array<int, array> $pool
     */
    public static function findById(array $pool, string $id): ?array
    {
        foreach ($pool as $link) {
            if (ArrayHelper::getValue($link, 'id') === $id) {
                return $link;
            }
        }
        return null;
    }
}
