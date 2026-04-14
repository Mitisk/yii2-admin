<?php

declare(strict_types=1);

use Mitisk\Yii2Admin\core\components\LinkPalette;
use yii\helpers\Html;
use yii\helpers\Json;

/* @var $this yii\web\View */
/* @var $model \Mitisk\Yii2Admin\models\AdminModel */

$links = is_array($model->links) ? $model->links : [];

$icons   = LinkPalette::icons();
$colors  = LinkPalette::colors();
$targets = LinkPalette::targets();
?>

<div class="tab-pane fade" id="tab-links" role="tabpanel">
    <div class="wg-box mb-20">
        <div class="links-editor__head mb-16">
            <div>
                <h4 style="margin:0;">Пользовательские ссылки-кнопки</h4>
                <div class="link-card__hint" style="margin-top:4px;">
                    Создавайте переиспользуемые кнопки-ссылки.
                    Их можно добавлять в колонки таблицы и в визуальный холст формы.
                    В URL доступны алиасы <code>{id}</code>, <code>{name}</code>
                    и любые другие атрибуты записи в фигурных скобках.
                </div>
            </div>
            <button type="button" class="tf-button style-1 js-link-add">
                <i class="icon-plus"></i> Добавить ссылку
            </button>
        </div>

        <div class="links-editor" id="links-editor-list"></div>

        <div class="links-editor__empty js-links-empty"
             <?= $links ? 'style="display:none;"' : '' ?>>
            Пока нет ни одной ссылки. Нажмите «Добавить ссылку», чтобы начать.
        </div>

        <?= Html::hiddenInput(
            Html::getInputName($model, 'links'),
            Json::encode(array_values($links)),
            ['id' => 'adminmodel-links']
        ) ?>
    </div>
</div>

<?php /* Модалка выбора иконки */ ?>
<div class="modal fade" id="linkIconModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Выбор иконки</h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="link-icon-grid" id="linkIconGrid">
                    <?php foreach ($icons as $ic) : ?>
                        <div class="link-icon-grid__item"
                             data-icon="<?= Html::encode($ic) ?>"
                             title="<?= Html::encode($ic) ?>">
                            <i class="<?= Html::encode($ic) ?>"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="tf-button style-2 tf-info js-link-icon-clear">
                    Без иконки
                </button>
                <button type="button" class="tf-button" data-bs-dismiss="modal">
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJsVar('adminLinkColorsMap', $colors);
$this->registerJsVar('adminLinkTargetsMap', $targets);
$this->registerJsVar('adminLinkIconsList', array_values($icons));
$this->registerJsVar('adminLinksInitial', array_values($links));
?>
