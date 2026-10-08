<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\widgets;

use Mitisk\Yii2Admin\components\AdminBarComponent;
use Mitisk\Yii2Admin\components\ContentBlockService;
use Mitisk\Yii2Admin\enums\BlockType;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;

/**
 * Текстовый блок раздела «Контент» в шаблоне сайта.
 *
 * ```php
 * <?= ContentBlock::widget(['key' => 'header.phone', 'default' => '+7 (495) 000-00-00']) ?>
 * <?= ContentBlock::widget(['key' => 'home.intro', 'type' => BlockType::Html, 'default' => '<p>Текст</p>']) ?>
 * <?= ContentBlock::widget([
 *     'key' => 'home.benefits', 'type' => BlockType::List, 'name' => 'Преимущества',
 *     'itemFields' => ['title', 'text', 'image'], 'itemView' => '@app/views/site/_benefit.php',
 * ]) ?>
 * ```
 * Блока нет в БД — он создаётся со значением `default`. Выключен — пустая строка.
 * Администратору с правом `editContent` вывод оборачивается для правки через Admin Bar.
 */
class ContentBlock extends Widget
{
    public string $key = '';

    public BlockType|string $type = BlockType::Text;

    /** @var mixed Строка или массив (для link/list) — значение для автосоздания. */
    public mixed $default = '';

    public string $name = '';

    public string $hint = '';

    public string $group = '';

    /** Тег обёртки; null — по типу ({@see BlockType::defaultTag()}). */
    public ?string $tag = null;

    /** HTML-атрибуты обёртки. Без атрибутов и без Admin Bar обёртка не выводится. */
    public array $options = [];

    /** HTML-атрибуты `<a>`/`<img>` для ссылки и картинки. */
    public array $contentOptions = [];

    public bool $nl2br = false;

    /** @var list<string> Поля пункта списка ({@see \Mitisk\Yii2Admin\dto\ListItem::FIELDS}). */
    public array $itemFields = [];

    /** Шаблон пункта списка: получает `$item` и `$index`. */
    public ?string $itemView = null;

    /** @var callable|null fn(array $item, int $index): string */
    public $itemTemplate = null;

    public function run(): string
    {
        $type = $this->type instanceof BlockType ? $this->type : (BlockType::tryFrom($this->type) ?? BlockType::Text);

        /** @var ContentBlockService $blocks */
        $blocks = Yii::$app->get('blocks');
        $html = $blocks->renderBlock(
            $this->key,
            $type,
            $this->default,
            [
                'contentOptions' => $this->contentOptions,
                'nl2br' => $this->nl2br,
                'itemView' => $this->itemView,
                'itemTemplate' => $this->itemTemplate,
                'view' => $this->getView(),
            ],
            ['name' => $this->name, 'hint' => $this->hint, 'group' => $this->group, 'itemFields' => $this->itemFields]
        );
        if ($html === null) {
            return '';
        }

        $options = $this->options;
        $used = $blocks->getUsedBlocks()[$this->key] ?? null;
        $bar = Yii::$app->has('adminBar') ? Yii::$app->get('adminBar') : null;
        if ($used !== null && $bar instanceof AdminBarComponent && $bar->shouldWrapBlocks()) {
            $options['data'] = array_merge($options['data'] ?? [], [
                'ab-block' => $this->key,
                'ab-type' => $used['type'],
                'ab-label' => $used['name'],
            ]);
        }
        if ($options === []) {
            return $html;
        }
        $realType = BlockType::tryFrom($used['type'] ?? '') ?? $type;
        return Html::tag($this->tag ?? $realType->defaultTag(), $html, $options);
    }
}
