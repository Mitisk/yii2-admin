<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\enums;

/**
 * Тип текстового блока раздела «Контент».
 *
 * Значение хранится в `content_block.type`. Формат `content_block.value`
 * для каждого типа описан в {@see \Mitisk\Yii2Admin\components\content\BlockValueCodec}.
 */
enum BlockType: string
{
    case Text = 'text';
    case Html = 'html';
    case Image = 'image';
    case Link = 'link';
    case List = 'list';

    /**
     * Подпись для админки.
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => 'Текст',
            self::Html => 'HTML',
            self::Image => 'Картинка',
            self::Link => 'Ссылка',
            self::List => 'Список',
        };
    }

    /**
     * Значение хранится как JSON (картинка, ссылка, список).
     */
    public function isStructured(): bool
    {
        return in_array($this, [self::Image, self::Link, self::List], true);
    }

    /**
     * Правится прямо на странице сайта; остальные типы — в модальном окне.
     */
    public function isInlineEditable(): bool
    {
        return $this === self::Text;
    }

    /**
     * Тег обёртки по умолчанию: строчные типы — span, блочные — div.
     */
    public function defaultTag(): string
    {
        return in_array($this, [self::Text, self::Link], true) ? 'span' : 'div';
    }

    /**
     * Список для выпадающих списков: значение => подпись.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }
        return $out;
    }
}
