<?php
use Mitisk\Yii2Admin\components\AdminDashboardHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
?>
<div class="popup-wrap user type-header">
    <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton3" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="header-user wg-user">
                <span class="image">
                    <img src="<?= Yii::$app->user->identity->image ?>" alt="">
                </span>
                <span class="flex flex-column">
                    <span class="body-title mb-2"><?= Html::encode(Yii::$app->user->identity->name) ?></span>
                    <span class="text-tiny"><?= AdminDashboardHelper::getCurrentUserRoleName() ?></span>
                </span>
            </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton3" >
            <li>
                <a href="/admin/user/update/?id=<?= Yii::$app->user->id ?>" class="user-item">
                    <div class="icon">
                        <i class="icon-user"></i>
                    </div>
                    <div class="body-title-2">Профиль</div>
                </a>
            </li>
            <li>
                <?= Html::a(
                    '<div class="icon"><i class="icon-log-out"></i></div><div class="body-title-2">Выйти</div>',
                    '/admin/logout/',
                    [
                        'class' => 'user-item',
                        'onclick' => "sendPostRequest(event); return false;",
                    ]
                ) ?>
            </li>
        </ul>
    </div>
</div>

<script>
    function sendPostRequest(event) {
        event.preventDefault(); // Отменяем стандартное поведение ссылки

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = event.target.closest('a').href;

        // Добавляем CSRF-токен (если он используется)
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '<?= \Yii::$app->request->csrfParam ?>';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    }
</script>