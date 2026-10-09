<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\components\pages;

use yii\base\Event;

/**
 * Сайт дописывает в `$entries` свои адреса: `['loc' => '/news/1', 'lastmod' => 1700000000]`.
 */
class PageSitemapEvent extends Event
{
    /** @var list<array{loc: string, lastmod: int|null}> */
    public array $entries = [];
}
