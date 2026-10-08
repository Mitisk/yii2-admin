<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\content;

use Mitisk\Yii2Admin\dto\ImageValue;
use Mitisk\Yii2Admin\dto\LinkValue;
use Mitisk\Yii2Admin\dto\ListItem;
use Mitisk\Yii2Admin\enums\BlockType;
use yii\base\InvalidConfigException;
use yii\base\View;
use yii\helpers\Html;

/**
 * HTML блока без обёртки Admin Bar. Не ходит в БД: URL картинки получает
 * через переданное замыкание, поэтому тестируется без приложения.
 */
final class BlockRenderer
{
    /**
     * @param \Closure(int): ?string $imageUrl URL картинки по id записи `file` или null.
     */
    public function __construct(private readonly \Closure $imageUrl)
    {
    }

    /**
     * @param string|LinkValue|ImageValue|list<ListItem> $value Декодированное значение.
     * @param array{contentOptions?: array, nl2br?: bool, itemTemplate?: callable, itemView?: string, view?: View} $opts
     */
    public function render(BlockType $type, string|LinkValue|ImageValue|array $value, array $opts = []): string
    {
        $contentOptions = $opts['contentOptions'] ?? [];

        return match ($type) {
            BlockType::Text => !empty($opts['nl2br']) ? nl2br(Html::encode((string)$value)) : Html::encode((string)$value),
            BlockType::Html => (string)$value,
            BlockType::Link => $value instanceof LinkValue ? $this->link($value, $contentOptions) : '',
            BlockType::Image => $value instanceof ImageValue ? $this->image($value, $contentOptions) : '',
            BlockType::List => is_array($value) ? $this->items($value, $opts) : '',
        };
    }

    /**
     * Данные пункта для шаблона сайта: id картинки заменён на URL.
     *
     * @return array{title: string, text: string, url: string, image: string|null}
     */
    public function itemData(ListItem $item): array
    {
        return [
            'title' => $item->title,
            'text' => $item->text,
            'url' => LinkValue::isSafeUrl($item->url) ? $item->url : '',
            'image' => $item->image === null ? null : ($this->imageUrl)($item->image),
        ];
    }

    private function link(LinkValue $link, array $options): string
    {
        if ($link->isEmpty()) {
            return '';
        }
        if ($link->target === '_blank') {
            $options += ['target' => '_blank', 'rel' => 'noopener'];
        }
        $href = LinkValue::isSafeUrl($link->url) ? $link->url : '#';
        return Html::a(Html::encode($link->text !== '' ? $link->text : $link->url), $href, $options);
    }

    private function image(ImageValue $image, array $options): string
    {
        $url = $image->fileId === null ? null : ($this->imageUrl)($image->fileId);
        if ($url === null || $url === '') {
            return '';
        }
        $options += ['alt' => $image->alt, 'loading' => 'lazy'];
        if ($image->title !== '') {
            $options += ['title' => $image->title];
        }
        return Html::img($url, $options);
    }

    /**
     * @param list<ListItem> $items
     * @param array{itemTemplate?: callable, itemView?: string, view?: View} $opts
     */
    private function items(array $items, array $opts): string
    {
        if ($items === []) {
            return '';
        }
        if (isset($opts['itemTemplate']) && is_callable($opts['itemTemplate'])) {
            $out = '';
            foreach ($items as $i => $item) {
                $out .= (string)call_user_func($opts['itemTemplate'], $this->itemData($item), $i);
            }
            return $out;
        }
        if (!empty($opts['itemView'])) {
            $view = $opts['view'] ?? null;
            if (!$view instanceof View) {
                throw new InvalidConfigException('Для itemView нужен объект View в опции "view".');
            }
            $out = '';
            foreach ($items as $i => $item) {
                $out .= $view->render($opts['itemView'], ['item' => $this->itemData($item), 'index' => $i]);
            }
            return $out;
        }
        $labels = array_map(static fn(ListItem $i): string => $i->title !== '' ? $i->title : $i->text, $items);
        return Html::ul(array_filter($labels, static fn(string $s): bool => $s !== ''));
    }
}
