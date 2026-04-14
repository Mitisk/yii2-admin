<?php
/**
 * Link-кнопка — элемент визуального холста, ведущий по пользовательской ссылке.
 *
 * @category Field
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 * @php      8.1
 */

declare(strict_types=1);

namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\core\components\LinkRenderer;
use yii\helpers\ArrayHelper;

/**
 * Поле-«ссылка-кнопка» в визуальном холсте.
 *
 * @category Field
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 */
class LinkField extends Field
{
    /**
     * Идентификатор ссылки в пуле компонента (admin_model.links).
     *
     * @var string
     */
    public $link_id = '';

    /**
     * Инициализирует поле, подставляя label из конфигурации ссылки.
     *
     * @return void
     */
    public function init(): void
    {
        parent::init();
        if (!$this->label) {
            $link = $this->_getLink();
            $this->label = (string)(
                $link['title']
                    ?? $link['url']
                    ?? 'Ссылка'
            );
        }
    }

    /**
     * Render for form.
     *
     * @return string
     */
    public function renderField(): string
    {
        return $this->_render('form');
    }

    /**
     * Render for detail-view.
     *
     * @return string
     */
    public function renderView(): string
    {
        return $this->_render('view');
    }

    /**
     * Получить конфигурацию ссылки из пула компонента.
     *
     * @return array|null
     */
    private function _getLink(): ?array
    {
        $id = (string)(
            $this->link_id
                ?: ArrayHelper::getValue((array)$this->input, 'link_id', '')
        );
        if ($id === '' || $this->model === null) {
            return null;
        }
        return $this->model->getLinkById($id);
    }

    /**
     * Полный рендер кнопки.
     *
     * @param string $ctx Контекст рендера (form|view).
     *
     * @return string
     */
    private function _render(string $ctx): string
    {
        $link = $this->_getLink();
        if ($link === null) {
            return '<span class="admin-link-btn admin-link-btn--sm pastel-red">'
                . '<i class="icon-x"></i> Ссылка удалена</span>';
        }
        $modelInstance = $this->model ? $this->model->getModel() : null;
        return LinkRenderer::render($link, $modelInstance, $ctx);
    }
}
