<?php
/**
 * User-picker field view.
 *
 * @var \Mitisk\Yii2Admin\fields\UserField            $field
 * @var \Mitisk\Yii2Admin\core\models\AdminModel      $model
 * @var string                                         $fieldId
 * @var string                                         $searchUrl
 */

use Mitisk\Yii2Admin\models\AdminUser;
use yii\helpers\Html;

$userId = Html::getAttributeValue($model->getModel(), $field->name);

$displayName   = '';
$currentAvatar = '';
$currentRoles  = '';

if ($userId) {
    $user = AdminUser::findOne($userId);
    if ($user) {
        $displayName   = $user->name ?: $user->username;
        $currentAvatar = $user->getAvatar();
        $auth          = Yii::$app->authManager;
        $roles         = $auth ? array_keys($auth->getRolesByUser($user->id)) : [];
        $currentRoles  = implode(', ', $roles);
    }
}
?>
<div class="form-group">
    <label class="body-title mb-10" for="<?= $fieldId ?>">
        <?= Html::encode($field->label) ?>
        <?php if ($field->required) : ?><span class="tf-color-1">*</span><?php endif; ?>
    </label>

    <?= Html::activeHiddenInput($model->getModel(), $field->name, ['id' => $fieldId]) ?>

    <div class="user-field-wrap"
         data-field-id="<?= Html::encode($fieldId) ?>"
         data-search-url="<?= Html::encode($searchUrl) ?>">

        <div class="user-field-selected"<?= $userId && $displayName ? '' : ' style="display:none;"' ?>>
            <?php if ($userId && $displayName) : ?>
                <img src="<?= Html::encode($currentAvatar) ?>" class="user-field-avatar"
                     alt="<?= Html::encode($displayName) ?>">
                <div class="user-field-info">
                    <div class="user-field-name"><?= Html::encode($displayName) ?></div>
                    <?php if ($currentRoles) : ?>
                        <div class="user-field-role"><?= Html::encode($currentRoles) ?></div>
                    <?php endif; ?>
                </div>
                <button type="button" class="user-field-clear" title="Убрать">×</button>
            <?php endif; ?>
        </div>

        <div class="user-field-search"<?= $userId && $displayName ? ' style="display:none;"' : '' ?>>
            <input type="text" class="form-control user-field-input"
                   placeholder="Введите имя, логин или email..."
                   autocomplete="off">
            <div class="user-field-dropdown"></div>
        </div>
    </div>

    <div class="col-lg-7 invalid-feedback"></div>
</div>

<?= $this->render('_help_block', ['field' => $field]) ?>

<?php
$this->registerCss(<<<'CSS'
.user-field-wrap { position: relative; }
.user-field-selected {
    display: flex; align-items: center; gap: 10px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 8px; padding: 8px 12px;
}
.user-field-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
}
.user-field-info { flex-grow: 1; min-width: 0; }
.user-field-name { font-weight: 600; font-size: 14px; }
.user-field-role { font-size: 12px; color: #64748b; }
.user-field-clear {
    background: none; border: none; color: #94a3b8;
    font-size: 20px; line-height: 1; cursor: pointer;
    padding: 0 4px; flex-shrink: 0;
}
.user-field-clear:hover { color: #ef4444; }
.user-field-dropdown {
    display: none; position: absolute; top: 100%; left: 0; right: 0;
    z-index: 999; background: #fff; border: 1px solid #e2e8f0;
    border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.08);
    max-height: 280px; overflow-y: auto; margin-top: 4px;
}
.user-field-option {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; cursor: pointer; transition: background .12s;
}
.user-field-option:hover { background: #f1f5f9; }
.user-field-option img {
    width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
}
.user-field-option-name { font-weight: 600; font-size: 14px; }
.user-field-option-meta { font-size: 12px; color: #64748b; }
.user-field-empty { padding: 12px 14px; color: #94a3b8; font-size: 13px; }
CSS
);

$js = <<<'JS'
(function () {
    document.querySelectorAll('.user-field-wrap').forEach(function (wrap) {
        var fieldId   = wrap.dataset.fieldId;
        var searchUrl = wrap.dataset.searchUrl;
        var hidden    = document.getElementById(fieldId);
        var selected  = wrap.querySelector('.user-field-selected');
        var searchBox = wrap.querySelector('.user-field-search');
        var input     = wrap.querySelector('.user-field-input');
        var dropdown  = wrap.querySelector('.user-field-dropdown');
        var timer;

        function esc(s) {
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function showUser(u) {
            var roles = (u.roles || []).join(', ');
            selected.innerHTML =
                '<img src="' + esc(u.avatar) + '" class="user-field-avatar" alt="">' +
                '<div class="user-field-info">' +
                    '<div class="user-field-name">' + esc(u.name) + '</div>' +
                    (roles ? '<div class="user-field-role">' + esc(roles) + '</div>' : '') +
                '</div>' +
                '<button type="button" class="user-field-clear" title="Убрать">\u00d7</button>';
            selected.style.display = 'flex';
            searchBox.style.display = 'none';
            dropdown.style.display = 'none';
            hidden.value = u.id;
            input.value  = '';
            selected.querySelector('.user-field-clear').addEventListener('click', clearUser);
        }

        function clearUser() {
            hidden.value = '';
            selected.innerHTML = '';
            selected.style.display = 'none';
            searchBox.style.display = '';
            input.value = '';
            input.focus();
        }

        var initClear = selected.querySelector('.user-field-clear');
        if (initClear) initClear.addEventListener('click', clearUser);

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch(searchUrl + '?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (users) {
                        dropdown.innerHTML = '';
                        if (!users.length) {
                            dropdown.innerHTML =
                                '<div class="user-field-empty">Пользователи не найдены</div>';
                        } else {
                            users.forEach(function (u) {
                                var roles = (u.roles || []).join(', ');
                                var opt   = document.createElement('div');
                                opt.className = 'user-field-option';
                                opt.innerHTML =
                                    '<img src="' + esc(u.avatar) + '" alt="">' +
                                    '<div>' +
                                        '<div class="user-field-option-name">'
                                            + esc(u.name) + '</div>' +
                                        '<div class="user-field-option-meta">'
                                            + esc(u.login)
                                            + (roles ? ' &middot; ' + esc(roles) : '')
                                            + '</div>' +
                                    '</div>';
                                opt.addEventListener('click', function () { showUser(u); });
                                dropdown.appendChild(opt);
                            });
                        }
                        dropdown.style.display = 'block';
                    })
                    .catch(function () { dropdown.style.display = 'none'; });
            }, 280);
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) dropdown.style.display = 'none';
        });
    });
}());
JS;
$this->registerJs($js);
?>
