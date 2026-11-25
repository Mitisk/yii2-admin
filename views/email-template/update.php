<?php
/**
 * @var yii\web\View $this
 * @var Mitisk\Yii2Admin\models\EmailTemplate $model
 * @var Mitisk\Yii2Admin\components\mail\MailEventInterface|null $event
 * @var array<string, array<string, mixed>> $rows
 * @var array{email: string, name: string} $sender
 * @var bool $smtpReady
 * @var bool $hasLayout
 * @var string[] $adminEmails
 */

$this->title = $model->name !== null && $model->name !== '' ? (string) $model->name : 'Шаблон письма';
$this->params['breadcrumbs'][] = ['label' => 'Шаблоны писем', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Изменение';

echo $this->render('_form', [
    'model' => $model,
    'event' => $event,
    'rows' => $rows,
    'sender' => $sender,
    'smtpReady' => $smtpReady,
    'hasLayout' => $hasLayout,
    'adminEmails' => $adminEmails,
]);
