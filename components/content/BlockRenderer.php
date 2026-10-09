<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\content;

use Mitisk\Yii2Admin\dto\ImageValue;
use Mitisk\Yii2Admin\dto\LinkValue;
use Mitisk\Yii2Admin\enums\BlockType;
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
     * @param string|LinkValue|ImageValue $value Декодированное значение.
     * @param array{contentOptions?: array, nl2br?: bool} $opts
     */
    public function render(BlockType $type, string|LinkValue|ImageValue $value, array $opts = []): string
    {
        $contentOptions = $opts['contentOptions'] ?? [];

        return match ($type) {
            BlockType::Text => !empty($opts['nl2br']) ? nl2br(Html::encode((string)$value)) : Html::encode((string)$value),
            BlockType::Html => (string)$value,
            BlockType::Link => $value instanceof LinkValue ? $this->link($value, $contentOptions) : '',
            BlockType::Image => $value instanceof ImageValue ? $this->image($value, $contentOptions) : '',
        };
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
}
