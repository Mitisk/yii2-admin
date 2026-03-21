<?php
/**
 * Base field widget — resolves the concrete field class and delegates rendering.
 *
 * @category Field
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: $Id$
 * @link     https://github.com/mitisk/yii2-admin
 * @php      8.0
 */

namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\core\models\AdminModel;
use Yii;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * Base field class — resolves a concrete subclass from canvas JSON input.
 *
 * @category Field
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 */
class Field extends Widget
{
    /**
     * Initial field settings array.
     *
     * @var array
     */
    public $input;

    /**
     * Admin model instance.
     *
     * @var AdminModel
     */
    public $model;

    /**
     * Field type identifier.
     *
     * @var string
     */
    public $type;

    /**
     * Whether the field is required.
     *
     * @var boolean
     */
    public $required;

    /**
     * Field label.
     *
     * @var string
     */
    public $label;

    /**
     * CSS class name for the field.
     *
     * @var string
     */
    public $className;

    /**
     * Block width: 100, 50, 33, or 25.
     *
     * @var string
     */
    public $width = '100';

    /**
     * Field attribute name.
     *
     * @var string
     */
    public $name;

    /**
     * Help text / description.
     *
     * @var string
     */
    public $description;

    /**
     * Whether the field is read-only.
     *
     * @var boolean
     */
    public $readonly;

    /**
     * Whether to show time alongside date (used by DateField).
     *
     * @var boolean
     */
    public $withTime = false;

    /**
     * Field subtype (e.g. h2/h3 for headers, p for paragraphs).
     *
     * @var string|null
     */
    public $subtype;

    /**
     * Placeholder text.
     *
     * @var string
     */
    public $placeholder;

    /**
     * Whether RBAC access control is used.
     *
     * @var boolean
     */
    public $access;

    /**
     * Comma-separated list of roles that can see this field.
     *
     * @var string
     */
    public $role;

    /**
     * Current field value.
     *
     * @var string
     */
    public $value;

    /**
     * Field element id.
     *
     * @var string
     */
    public $fieldId;

    /**
     * Wrapper CSS class.
     *
     * @var string
     */
    public $wrapperClass = '';

    /**
     * Check field accessibility for RBAC roles.
     *
     * @return bool
     */
    protected function canRender() : bool
    {
        // роли через запятую: 'admin, manager, @'
        $raw = (string)($this->role ?? '');
        $roles = array_values(array_filter(array_map('trim', explode(',', $raw))));

        // если роли не заданы — считаем, что поле видно всем
        if (empty($roles)) {
            return true;
        }

        // OR-логика: достаточно одного совпадения
        foreach ($roles as $role) {
            if ($role === '@') {
                if (!Yii::$app->user->isGuest) {
                    return true;
                }
                continue;
            }
            if ($role === '?') {
                if (Yii::$app->user->isGuest) {
                    return true;
                }
                continue;
            }
            if (Yii::$app->user->can($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns column config for GridView.
     *
     * @param string $column Column attribute name.
     *
     * @return array
     */
    public function getListData(string $column): array
    {
        $fieldClass = $this->_buildField();
        $fieldClass->model = $this->model;

        $config = $fieldClass->renderList($column)
            + ['visible' => $fieldClass->canRender()];
        if (!isset($config['label']) && $fieldClass->label) {
            $config['label'] = $fieldClass->label;
        }
        return $config;
    }

    /**
     * Returns rendered HTML for the detail-view.
     *
     * @return string
     */
    public function getViewData(): string
    {
        $fieldClass = $this->_buildField();
        $fieldClass->model = $this->model;

        if (!$fieldClass->canRender()) {
            return '';
        }

        $hasAttr = $fieldClass->name
            && $this->model->getModel()->hasAttribute($fieldClass->name);
        if ($hasAttr) {
            $fieldClass->fieldId = Html::getInputId(
                $this->model->getModel(),
                $fieldClass->name
            );
        } else {
            $fieldClass->fieldId = Yii::$app->security->generateRandomString();
        }

        return $fieldClass->renderView();
    }

    /**
     * Returns rendered HTML for the edit form.
     *
     * @return string
     */
    public function getFormInput(): string
    {
        $fieldClass = $this->_buildField();
        $fieldClass->model = $this->model;

        if (!$fieldClass->canRender()) {
            return '';
        }

        $hasAttr = $fieldClass->name
            && $this->model->getModel()->hasAttribute($fieldClass->name);
        if ($hasAttr) {
            $fieldClass->fieldId = Html::getInputId(
                $this->model->getModel(),
                $fieldClass->name
            );
        } else {
            $fieldClass->fieldId = Yii::$app->security->generateRandomString();
        }

        $widthMap = ['50' => 'col-md-6', '33' => 'col-md-4', '25' => 'col-md-3'];
        $colClass = $widthMap[(string)$fieldClass->width] ?? '';

        $class = trim($colClass . ' ' . $fieldClass->wrapperClass);

        return '<fieldset class="' . $class . '">'
            . $fieldClass->renderField()
            . '</fieldset>';
    }

    /**
     * Builds and returns the concrete field subclass instance.
     *
     * @return Field
     */
    private function _buildField(): Field
    {
        $input = $this->_normalizeCanvasInput($this->input);

        $name = str_replace('-', ' ', ArrayHelper::getValue($input, 'type', 'text'));

        $fieldName = static::resolveFieldClass(
            str_replace(' ', '', ucfirst($name)) . 'Field'
        );

        // @var Field $field
        $field = Yii::createObject(['class' => $fieldName], [$input]);
        $field->model = $this->model;

        if (empty($field->label)) {
            $field->label = $this->getLabel();
        }

        return $field;
    }

    /**
     * Returns the display label for the current field.
     *
     * @return string
     */
    public function getLabel(): string
    {
        $fieldName = ArrayHelper::getValue($this->input, 'name');
        if ($fieldName) {
            $customLabels = $this->model->component->attribute_labels;
            if (isset($customLabels[$fieldName])
                && $customLabels[$fieldName] !== ''
            ) {
                return $customLabels[$fieldName];
            }
            return $this->model->getModel()->getAttributeLabel($fieldName);
        }
        return (string)(ArrayHelper::getValue($this->input, 'label') ?? '');
    }

    /**
     * Maps canvas-specific properties to Field-expected ones and strips unknowns.
     *
     * @param array $input Raw item from AdminModel::data JSON.
     *
     * @return array
     */
    private function _normalizeCanvasInput(array $input): array
    {
        // ── Content blocks (header / paragraph / divider) ────────────────
        if (!empty($input['isContent'])) {
            // canvas 'text' → Field 'label'
            if (isset($input['text']) && !isset($input['label'])) {
                $input['label'] = $input['text'];
            }
            // canvas 'tag' (h1…h6) → Field 'subtype'
            if (isset($input['tag']) && !isset($input['subtype'])) {
                $input['subtype'] = $input['tag'];
            }
            // paragraph has no explicit tag — default to <p>
            if (($input['type'] ?? '') === 'paragraph' && empty($input['subtype'])) {
                $input['subtype'] = 'p';
            }
            // canvas type 'divider' → 'hrline' (matches HrLineField)
            if (($input['type'] ?? '') === 'divider') {
                $input['type'] = 'hrline';
            }
        }

        // visual / html → textarea с подтипом viewtype
        if (in_array($input['type'] ?? '', ['visual', 'html'], true)) {
            $input['viewtype'] = $input['type'];
            $input['type'] = 'textarea';
        }

        // hint → description; hint wins over an empty existing description
        if (!empty($input['hint'])) {
            $input['description'] = $input['hint'];
        } elseif (!array_key_exists('description', $input)) {
            $input['description'] = '';
        }
        // roles (array) → role (comma-separated string)
        if (isset($input['roles']) && !isset($input['role'])) {
            $input['role'] = implode(',', (array)$input['roles']);
        }
        // Select-специфичные маппинги (только для type=select)
        $isSelect = ($input['type'] ?? '') === 'select';
        if ($isSelect) {
            if (isset($input['selectMultiple'])
                && !isset($input['multiple'])
            ) {
                $input['multiple'] = $input['selectMultiple'];
            }
            if (!empty($input['selectSourceVal'])
                && empty($input['publicStaticMethod'])
            ) {
                $input['publicStaticMethod'] = $input['selectSourceVal'];
            }
            if (!empty($input['selectSaveMethod'])
                && empty($input['publicSaveMethod'])
            ) {
                $input['publicSaveMethod'] = $input['selectSaveMethod'];
            }
            // entity + мультивыбор: если метод сохранения не указан,
            // используем тот же метод что и для загрузки.
            // Только для multiple — hasOne сохраняет FK напрямую.
            if (($input['selectSourceType'] ?? '') === 'entity'
                && !empty($input['multiple'])
                && empty($input['publicSaveMethod'])
                && !empty($input['publicStaticMethod'])
            ) {
                $input['publicSaveMethod'] = $input['publicStaticMethod'];
            }
            // Очищаем старый формат Class::method()
            // → оставляем только имя метода
            foreach (['publicStaticMethod', 'publicSaveMethod'] as $k) {
                if (!empty($input[$k]) && str_contains($input[$k], '::')) {
                    $input[$k] = rtrim(
                        substr($input[$k], strrpos($input[$k], '::') + 2),
                        '()'
                    );
                }
            }
        }
        // File: fileMultiple → multiple
        if (($input['type'] ?? '') === 'file'
            && isset($input['fileMultiple'])
            && !isset($input['multiple'])
        ) {
            $input['multiple'] = $input['fileMultiple'];
        }
        // Strip canvas-only keys that Field and its subclasses don't declare
        unset(
            $input['id'], $input['isContent'],
            $input['roles'], $input['hint'],
            $input['selectMultiple'], $input['selectSourceType'],
            $input['selectSourceVal'], $input['selectSaveMethod'],
            $input['fileMultiple'],
            $input['tag'], $input['text']
        );

        return $input;
    }

    /**
     * Resolves the full field class name from a short name.
     *
     * @param string $class Short or fully-qualified class name.
     *
     * @return string|null
     */
    public static function resolveFieldClass(string $class): string|null
    {
        $classname = 'Mitisk\\Yii2Admin\\fields\\Field';
        if (class_exists($class)) {
            $classname = $class;
        } elseif (class_exists('Mitisk\\Yii2Admin\\fields\\' . $class)) {
            $classname = 'Mitisk\\Yii2Admin\\fields\\' . $class;
        }
        return $classname;
    }

    /**
     * Returns column definition array for GridView.
     *
     * @param string $column Column attribute name.
     *
     * @return array
     */
    public function renderList(string $column): array
    {
        return [
            'attribute' => $column
        ];
    }

    /**
     * Renders field HTML for the edit form.
     *
     * @return string
     */
    public function renderField(): string
    {
        return '<div class="form-group">Нет описания поля ' . $this->name . '</div>';
    }

    /**
     * Renders field HTML for the detail view.
     *
     * @return string
     */
    public function renderView(): string
    {
        return '<div class="form-group">Нет описания поля ' . $this->name . '</div>';
    }

    /**
     * Handles post-save field logic.
     *
     * @return bool
     */
    public function afterSave() : bool
    {
        $fieldClass = $this->_buildField();
        $fieldClass->model = $this->model;

        return $fieldClass->save();
    }

    /**
     * Handles pre-delete field logic.
     *
     * @return bool
     */
    public function beforeDelete() : bool
    {
        $fieldClass = $this->_buildField();
        $fieldClass->model = $this->model;

        return $fieldClass->delete();
    }

    /**
     * Saves field-related data.
     *
     * @return bool
     */
    public function save() : bool
    {
        return true;
    }

    /**
     * Deletes field-related data.
     *
     * @return bool
     */
    public function delete() : bool
    {
        return true;
    }
}
