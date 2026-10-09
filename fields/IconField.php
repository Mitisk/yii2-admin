<?php

namespace Mitisk\Yii2Admin\fields;

use Yii;
use yii\helpers\Html;

/**
 * Поле выбора иконки из набора темы (icon-*). Значение — css-класс иконки
 * (строка в атрибуте модели). Пикер самодостаточный (без внешних библиотек):
 * иконки уже подключены глобально, список читается из icon/style.css.
 */
class IconField extends TextField
{
    /** @var string Ключ кэша списка иконок темы */
    public const CACHE_KEY = 'admin_theme_icons';

    /**
     * @inheritdoc
     * @return string
     */
    public function renderField(): string
    {
        return $this->render('icon', [
            'field' => $this,
            'model' => $this->model,
            'fieldId' => $this->fieldId,
            'icons' => self::getThemeIcons(),
        ]);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function renderView(): string
    {
        $value = (string)Html::getAttributeValue($this->model->getModel(), $this->name);
        if ($value === '') {
            return '-';
        }
        return '<i class="' . Html::encode($value) . '"></i> '
            . Html::encode($value);
    }

    /**
     * @inheritdoc
     * @param string $column Выводимое поле
     * @return array
     */
    public function renderList(string $column): array
    {
        return [
            'attribute' => $column,
            'format' => 'raw',
            'value' => function ($data) use ($column) {
                $value = (string)$data->{$column};
                return $value === ''
                    ? '-'
                    : '<i class="' . Html::encode($value) . '"></i>';
            },
        ];
    }

    /**
     * Список css-классов иконок темы (из icon/style.css), с кэшем.
     *
     * @return string[]
     */
    public static function getThemeIcons(): array
    {
        $cache = Yii::$app->cache ?? null;
        if ($cache) {
            $cached = $cache->get(self::CACHE_KEY);
            if ($cached !== false) {
                return $cached;
            }
        }

        $icons = [];
        $file = Yii::getAlias('@Mitisk/Yii2Admin/assets/icon/style.css', false);
        if ($file && is_file($file)) {
            $css = (string)file_get_contents($file);
            if (preg_match_all('/\.(icon-[a-z0-9-]+):before/', $css, $m)) {
                $icons = array_values(array_unique($m[1]));
                sort($icons);
            }
        }

        if ($cache) {
            $cache->set(self::CACHE_KEY, $icons, 3600);
        }
        return $icons;
    }
}
