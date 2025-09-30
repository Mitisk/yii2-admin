<?php
namespace Mitisk\Yii2Admin\widgets;

use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use Yii;

/**
 * UserRolesWidget - виджет для управления ролями пользователя
 * 
 * Использование:
 * <?= UserRolesWidget::widget([
 *     'userId' => $model->id,
 *     'availableRoles' => $availableRoles,
 *     'assignedRoles' => $assignedRoles,
 *     'multiple' => true,
 *     'options' => ['class' => 'form-control']
 * ]) ?>
 */
class UserRolesWidget extends Widget
{
    /**
     * @var int ID пользователя
     */
    public $userId;

    /**
     * @var array Доступные роли [name => description]
     */
    public $availableRoles = [];

    /**
     * @var array Назначенные роли [name => name]
     */
    public $assignedRoles = [];

    /**
     * @var bool Можно ли выбирать несколько ролей
     */
    public $multiple = true;

    /**
     * @var array HTML опции для элемента
     */
    public $options = [];

    /**
     * @var array Настройки для Select2 плагина
     */
    public $pluginOptions = [];

    /**
     * @var string Имя поля для формы
     */
    public $name = 'user_roles';

    /**
     * @var bool Показывать описание ролей
     */
    public $showDescription = true;

    /**
     * @var bool Показывать количество разрешений в роли
     */
    public $showPermissionsCount = false;

    public function init()
    {
        parent::init();

        if (empty($this->availableRoles)) {
            $this->availableRoles = $this->getAvailableRoles();
        }

        if ($this->userId && empty($this->assignedRoles)) {
            $this->assignedRoles = $this->getUserRoles($this->userId);
        }

        // Настройки по умолчанию для Select2
        $this->pluginOptions = ArrayHelper::merge([
            'allowClear' => true,
            'placeholder' => 'Выберите роли...',
            'closeOnSelect' => !$this->multiple,
            'tags' => false,
        ], $this->pluginOptions);

        // HTML опции по умолчанию
        $this->options = ArrayHelper::merge([
            'multiple' => $this->multiple,
            'class' => 'form-control',
        ], $this->options);
    }

    public function run()
    {
        $html = '';

        // Основной виджет выбора ролей
        $html .= $this->renderRoleSelector();

        // Показать текущие роли, если есть
        if (!empty($this->assignedRoles)) {
            $html .= $this->renderCurrentRoles();
        }

        // Показать информацию о разрешениях
        if ($this->showDescription || $this->showPermissionsCount) {
            $html .= $this->renderRoleDescriptions();
        }

        return $html;
    }

    /**
     * Рендеринг селектора ролей
     */
    protected function renderRoleSelector()
    {
        if (!Yii::$app->user->can('manageUserRoles')) {
            return Html::tag('div', 
                Html::tag('i', '', ['class' => 'icon-lock']) . ' Нет прав для управления ролями', 
                ['class' => 'alert alert-warning']
            );
        }

        return Select2::widget([
            'name' => $this->name,
            'value' => array_keys($this->assignedRoles),
            'data' => $this->availableRoles,
            'options' => $this->options,
            'pluginOptions' => $this->pluginOptions,
        ]);
    }

    /**
     * Рендеринг текущих ролей
     */
    protected function renderCurrentRoles()
    {
        $html = Html::tag('div', 'Текущие роли:', ['class' => 'roles-label']);
        $html .= Html::beginTag('div', ['class' => 'current-roles-list']);

        $authManager = Yii::$app->authManager;

        foreach ($this->assignedRoles as $roleName) {
            $role = $authManager->getRole($roleName);
            $roleHtml = Html::tag('span', Html::encode($roleName), ['class' => 'role-name']);

            if ($role && $role->description) {
                $roleHtml .= Html::tag('small', Html::encode($role->description), ['class' => 'role-description']);
            }

            $html .= Html::tag('div', $roleHtml, ['class' => 'role-item']);
        }

        $html .= Html::endTag('div');

        return $html;
    }

    /**
     * Рендеринг описаний ролей
     */
    protected function renderRoleDescriptions()
    {
        $html = Html::tag('div', 'Доступные роли:', ['class' => 'roles-info-label']);
        $html .= Html::beginTag('div', ['class' => 'roles-info']);

        $authManager = Yii::$app->authManager;

        foreach ($this->availableRoles as $roleName => $roleLabel) {
            $role = $authManager->getRole($roleName);
            $roleInfo = Html::tag('strong', Html::encode($roleLabel));

            if ($role && $role->description && $this->showDescription) {
                $roleInfo .= Html::tag('br') . Html::tag('small', Html::encode($role->description), ['class' => 'text-muted']);
            }

            if ($this->showPermissionsCount) {
                $permissions = $authManager->getPermissionsByRole($roleName);
                $count = count($permissions);
                $roleInfo .= Html::tag('span', " ({$count} разрешений)", ['class' => 'badge badge-secondary']);
            }

            $html .= Html::tag('div', $roleInfo, ['class' => 'role-info-item']);
        }

        $html .= Html::endTag('div');

        return $html;
    }

    /**
     * Получить доступные роли
     */
    protected function getAvailableRoles()
    {
        $authManager = Yii::$app->authManager;
        $roles = $authManager->getRoles();

        $availableRoles = [];
        foreach ($roles as $role) {
            if ($this->canAssignRole($role->name)) {
                $availableRoles[$role->name] = $role->description ?: $role->name;
            }
        }

        return $availableRoles;
    }

    /**
     * Получить роли пользователя
     */
    protected function getUserRoles($userId)
    {
        $authManager = Yii::$app->authManager;
        $roles = $authManager->getRolesByUser($userId);
        return ArrayHelper::map($roles, 'name', 'name');
    }

    /**
     * Проверить возможность назначения роли
     */
    protected function canAssignRole($roleName)
    {
        // Базовая проверка прав
        if (!Yii::$app->user->can('manageUserRoles')) {
            return false;
        }

        // Дополнительные ограничения можно добавить здесь
        // Например, только super admin может назначать admin роль
        if ($roleName === 'admin' && !Yii::$app->user->can('superAdmin')) {
            return false;
        }

        return true;
    }
}