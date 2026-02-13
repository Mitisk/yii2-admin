<?php
use Mitisk\Yii2Admin\components\AdminDashboardHelper;
use yii\helpers\Html;
use Mitisk\Yii2Admin\assets\AppAsset;

/* @var $this yii\web\View */

$identity = Yii::$app->user->identity;
$avatar = '';
$name = '';
if ($identity) {
    if (method_exists($identity, 'getAvatar')) {
        $avatar = $identity->getAvatar();
    } elseif (!empty($identity->image)) {
        $avatar = $identity->image;
    }
    
    if (isset($identity->name)) {
        $name = $identity->name;
    } elseif (isset($identity->username)) {
        $name = $identity->username;
    } else {
        $name = 'User';
    }
}

if (empty($avatar)) {
    $avatar = Yii::$app->assetManager->getBundle(AppAsset::class)->baseUrl . '/img/no_avatar.png';
}
?>
<div class="popup-wrap user type-header">
    <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton3" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="header-user wg-user">
                <span class="image avatar-wrap">
                    <img src="<?= $avatar ?>" alt="">
                    <span class="status-circle status-online"></span>
                </span>
                <span class="flex flex-column">
                    <span class="body-title mb-2"><?= Html::encode($name) ?></span>
                    <span class="text-tiny"><?= AdminDashboardHelper::getCurrentUserRoleName() ?></span>
                </span>
            </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton3">
            <li>
                <a href="<?= \yii\helpers\Url::to(['/admin/user/update', 'id' => Yii::$app->user->id]) ?>" class="user-item">
                    <div class="icon">
                        <i class="icon-user"></i>
                    </div>
                    <div class="body-title-2">Профиль</div>
                </a>
            </li>
            <li>
                <?= Html::a(
                    '<div class="icon"><i class="icon-log-out"></i></div><div class="body-title-2">Выйти</div>',
                    ['/admin/default/logout'],
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