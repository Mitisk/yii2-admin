<?php
/**
 * UserField — renders an autocomplete user-picker backed by the admin user table.
 *
 * @category Field
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: $Id$
 * @link     https://github.com/mitisk/yii2-admin
 */

namespace Mitisk\Yii2Admin\fields;

use Mitisk\Yii2Admin\models\AdminUser;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Field that stores a user ID and renders an autocomplete picker.
 *
 * @category Field
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 */
class UserField extends Field
{
    /**
     * Base RBAC roles that carry no meaningful info and should be hidden.
     */
    private const BASE_ROLES = ['guest', 'user'];

    /**
     * Returns meaningful roles for a user, excluding base RBAC aliases.
     *
     * @param int $userId User primary key.
     *
     * @return array
     */
    private static function _getRoles(int $userId): array
    {
        $auth = Yii::$app->authManager;
        if (!$auth) {
            return [];
        }

        $all      = array_keys($auth->getRolesByUser($userId));
        $filtered = array_values(array_diff($all, self::BASE_ROLES));

        return $filtered ?: [];
    }

    /**
     * Returns column definition for GridView with avatar + name + roles.
     *
     * @param string $column Column attribute name.
     *
     * @return array
     */
    public function renderList(string $column): array
    {
        return [
            'attribute' => $column,
            'format'    => 'raw',
            'filter'    => true, // text input, handled by applyColumnFilters
            'value'     => function ($data) use ($column) {
                $userId = $data->{$column};
                if (!$userId) {
                    return '-';
                }
                $user = AdminUser::findOne($userId);
                if (!$user) {
                    return Html::encode((string)$userId);
                }

                $roles = self::_getRoles($user->id);

                $avatar = Html::img(
                    $user->getAvatar(),
                    [
                        'width'  => 32,
                        'height' => 32,
                        'style'  => 'border-radius:50%;object-fit:cover;'
                            . 'flex-shrink:0;',
                    ]
                );

                $nameText = Html::encode(
                    $user->name ?: $user->username
                );

                if (Yii::$app->user->can('updateUsers')) {
                    $nameHtml = Html::a(
                        $nameText,
                        Url::to([
                            '/admin/user/update',
                            'id' => $user->id,
                        ]),
                        [
                            'class' => 'cell-user-link',
                            'title' => 'Редактировать',
                        ]
                    );
                } else {
                    $nameHtml = $nameText;
                }

                $roleStr = $roles
                    ? '<div class="cell-user-role">'
                        . Html::encode(implode(', ', $roles))
                        . '</div>'
                    : '';

                return '<div class="cell-user">'
                    . $avatar
                    . '<div><div class="cell-user-name">'
                    . $nameHtml . '</div>'
                    . $roleStr . '</div>'
                    . '</div>';
            },
        ];
    }

    /**
     * Renders the autocomplete form input.
     *
     * @return string
     */
    public function renderField(): string
    {
        return $this->render(
            'user',
            [
                'field'     => $this,
                'model'     => $this->model,
                'fieldId'   => $this->fieldId,
                'searchUrl' => Url::to(['/admin/ajax/user-search']),
            ]
        );
    }

    /**
     * Renders the detail-view cell.
     *
     * @return string
     */
    public function renderView(): string
    {
        $userId = Html::getAttributeValue($this->model->getModel(), $this->name);
        if (!$userId) {
            return '-';
        }

        $user = AdminUser::findOne($userId);
        if (!$user) {
            return Html::encode((string)$userId);
        }

        $roles = self::_getRoles($user->id);
        $roleStr = $roles
            ? '<div style="font-size:12px;color:#64748b;">'
                . Html::encode(implode(', ', $roles))
                . '</div>'
            : '';

        $avatar = Html::img(
            $user->getAvatar(),
            [
                'width'  => 40,
                'height' => 40,
                'style'  => 'border-radius:50%;object-fit:cover;flex-shrink:0;',
            ]
        );

        $name = Html::encode($user->name ?: $user->username);

        return '<div style="display:flex;align-items:center;gap:10px;">'
            . $avatar
            . '<div><div style="font-weight:600;">' . $name . '</div>'
            . $roleStr . '</div>'
            . '</div>';
    }
}
