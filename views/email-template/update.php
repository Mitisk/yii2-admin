<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model Mitisk\Yii2Admin\models\EmailTemplate */

$this->title = 'Редактирование: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Шаблоны писем', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Изменение';

//Получаем список Email администраторов
$adminEmailsStr = Yii::$app->settings->get('GENERAL', 'admin_email', '');
$adminEmails = [];
if ($adminEmailsStr) {
    // Разбиваем по запятой и убираем пробелы
    $adminEmails = array_map('trim', explode(',', $adminEmailsStr));
    // Убираем пустые значения
    $adminEmails = array_filter($adminEmails);
}
?>
<div class="flex items-center justify-between gap10 flex-wrap" style="margin-bottom: 20px;">
    <div class="wg-filter flex-grow">

    </div>
    <button type="button" class="tf-button style-2" data-bs-toggle="modal" data-bs-target="#testEmailModal">
        <i class="glyphicon glyphicon-envelope"></i> Отправить тестовое письмо
    </button>
    <?= \yii\helpers\Html::a("<i class=\"icon-settings\"></i>", ['/admin/settings/', 'modelName' => "Mitisk\Yii2Admin\models\MailTemplate"], ['class' => 'tf-button']) ?>
</div>

    <?= $this->render('_form', ['model' => $model]) ?>

<div class="modal fade" id="testEmailModal" tabindex="-1" aria-labelledby="testEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testEmailModalLabel">Тестовая отправка письма</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="testEmailError" style="display: none;"></div>
                <div class="alert alert-success d-none" id="testEmailSuccess" style="display: none;"></div>

                <form id="testEmailForm">
                    <div class="mb-3">
                        <label for="customEmailInput" class="body-title mb-10">Email получателя</label>
                        <input type="email" class="form-control" id="customEmailInput"
                               placeholder="name@example.com">
                    </div>
                </form>

                <?php if (!empty($adminEmails)): ?>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label text-muted">или быстрая отправка администраторам:</label>
                        <div class="d-grid gap-2">
                            <?php foreach ($adminEmails as $email): ?>
                                <button type="button" class="tf-button style-2 btn-admin-send w-full" data-email="<?= Html::encode($email) ?>">
                                    Отправить на <?= Html::encode($email) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="tf-button style-2 tf-info" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="tf-button w208" id="sendCustomEmailBtn">Отправить</button>
            </div>
        </div>
    </div>
</div>

<?php
// 3. JavaScript для обработки модального окна и AJAX
$templateId = $model->id;

$js = <<<JS
$(document).ready(function() {
    var templateId = {$templateId};
    var modal = $('#testEmailModal');
    var errorBlock = $('#testEmailError');
    var successBlock = $('#testEmailSuccess');
    var input = $('#customEmailInput');

    // Очистка сообщений при открытии модального окна
    modal.on('show.bs.modal', function () {
        errorBlock.hide().text('');
        successBlock.hide().text('');
        input.val('');
    });

    // Функция отправки AJAX
    function sendTestEmail(email) {
        if (!email) {
            showError('Введите Email адрес');
            return;
        }

        // Сброс состояний UI
        errorBlock.hide();
        successBlock.hide();
        var allButtons = modal.find('button');
        allButtons.prop('disabled', true); // Блокируем кнопки на время запроса

        $.ajax({
            url: '/admin/email-template/test-send/',
            type: 'POST',
            data: {
                id: templateId,
                email: email,
                _csrf: yii.getCsrfToken()
            },
            success: function(res) {
                if(res.success) {
                    showSuccess(res.message);
                } else {
                    showError(res.message);
                }
            },
            error: function(xhr) {
                showError('Ошибка сервера: ' + xhr.statusText);
            },
            complete: function() {
                allButtons.prop('disabled', false);
            }
        });
    }

    // Хелперы для отображения сообщений (адаптировано под классы bootstrap/твоего примера)
    function showError(msg) {
        errorBlock.removeClass('d-none').show().text(msg);
        successBlock.hide();
    }

    function showSuccess(msg) {
        successBlock.removeClass('d-none').show().text(msg);
        errorBlock.hide();
    }

    // 1. Обработка кнопки "Отправить" (из инпута)
    $('#sendCustomEmailBtn').on('click', function() {
        var email = input.val();
        sendTestEmail(email);
    });

    // 2. Обработка кнопок быстрой отправки на админские адреса
    $('.btn-admin-send').on('click', function() {
        var email = $(this).data('email');
        // Опционально: подставляем в инпут для наглядности
        input.val(email);
        sendTestEmail(email);
    });
});
JS;
$this->registerJs($js);
?>