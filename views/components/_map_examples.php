<div class="block-warning w-full mb-20" style="min-width: 195px;">
    <i class="fas fa-shield-alt"></i>
    <div class="body-title-2">Controller должен наследовать Mitisk\Yii2Admin\components\ExtAdminController</div>
</div>
<div class="wg-box mb-20 gap10">
    <div class="body-title">Примеры</div>
    <div class="body-text">
        <ul>
            <li class="body-text flex gap10 mb-3">Controller Id:</li>
            <li class="body-text flex gap10 mb-3" style="flex-wrap: wrap;">
                <span class="block-pending">reports</span>
                <span class="block-pending">webhook</span>
                <span class="block-pending">secure</span>
            </li>
            <li class="body-text flex gap10 mb-3">Class:</li>
            <li class="body-text flex gap10 mb-3" style="flex-wrap: wrap;">
                <span class="block-pending">app\controllers\ReportsController</span>
                <span class="block-pending">app\controllers\admin\WebhookController</span>
                <span class="block-pending">app\controllers\admin\SecureController</span>
            </li>
            <li class="body-text flex gap10 mb-3">Конфигурация:</li>
            <li class="body-text flex gap10 mb-3" style="flex-wrap: wrap;">
                <span class="block-pending">{"defaultAction":"index"}</span>
                <span class="block-pending">{"layout":"@app/modules/admin/views/layouts/main"}</span>
                <span class="block-pending">{"enableCsrfValidation":false}</span>
                <span class="block-pending">{"as access":{"class":"yii\filters\AccessControl","rules":[{"allow":true,"roles":["@"]}]}}</span>
            </li>
        </ul>
    </div>
</div>