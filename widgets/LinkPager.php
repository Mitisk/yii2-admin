<?php

namespace Mitisk\Yii2Admin\widgets;

use yii\helpers\Html;

class LinkPager extends \yii\widgets\LinkPager
{
    public $options = ['class' => 'modern-pagination'];

    public $linkContainerOptions = ['class' => 'pg-item'];

    public $linkOptions = ['class' => 'pg-link'];

    public $disabledListItemSubTagOptions = [
        'tag' => 'span',
        'class' => 'pg-link',
    ];

    public $prevPageLabel = '<i class="icon-chevron-left"></i>';

    public $nextPageLabel = '<i class="icon-chevron-right"></i>';

    public $prevPageCssClass = 'pg-item nav-btn';

    public $nextPageCssClass = 'pg-item nav-btn';

    public $activePageCssClass = 'active';

    public $disabledPageCssClass = 'disabled';

    /**
     * @inheritdoc
     */
    protected function renderPageButton(
        $label,
        $page,
        $class,
        $disabled,
        $active
    ): string {
        $options = $this->linkContainerOptions;
        $linkOptions = $this->linkOptions;

        $cssClass = $options['class'] ?? '';
        if ($class) {
            $cssClass .= ' ' . $class;
        }
        if ($active) {
            $cssClass .= ' ' . $this->activePageCssClass;
        }
        if ($disabled) {
            $cssClass .= ' ' . $this->disabledPageCssClass;
        }
        $options['class'] = trim($cssClass);

        if ($active || $disabled) {
            $tag = $this->disabledListItemSubTagOptions['tag']
                ?? 'span';
            $tagOptions = $this->disabledListItemSubTagOptions;
            unset($tagOptions['tag']);
            $linkHtml = Html::tag(
                $tag,
                $label,
                $tagOptions
            );
        } else {
            $linkHtml = Html::a(
                $label,
                $this->pagination->createUrl($page),
                $linkOptions
            );
        }

        return Html::tag('li', $linkHtml, $options);
    }
}
