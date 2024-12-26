<?php

namespace Mitisk\Yii2Admin\widgets;

use Yii;

class LinkPager extends \yii\widgets\LinkPager
{
    public $options = ['class' => 'wg-pagination'];
    public $prevPageLabel = '<i class="icon-chevron-left"></i>';

    public $nextPageLabel = '<i class="icon-chevron-right"></i>';
}


/*<ul class="pagination">
<li class="prev disabled"><span>«</span></li>
<li class="active"><a href="/admin/role/?page=1&amp;per-page=2" data-page="0">1</a></li>
<li><a href="/admin/role/?page=2&amp;per-page=2" data-page="1">2</a></li>
<li class="next"><a href="/admin/role/?page=2&amp;per-page=2" data-page="1">»</a></li>
</ul>*/

/*
 * <ul class="wg-pagination">
                <li>
                    <a href="#"></a>
                </li>
                <li>
                    <a href="#">1</a>
                </li>
                <li class="active">
                    <a href="#">2</a>
                </li>
                <li>
                    <a href="#">3</a>
                </li>
                <li>
                    <a href="#"><i class="icon-chevron-right"></i></a>
                </li>
            </ul>
 */