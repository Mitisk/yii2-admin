<?php
/**
 * Виджет аудит-лога на дашборде: счётчики + лента действий.
 *
 * @var \yii\web\View                         $this
 * @var int                                    $actionsToday
 * @var int                                    $errorsToday
 * @var int                                    $errorsWeek
 * @var \Mitisk\Yii2Admin\models\AuditLog[]    $recentActions
 */

use yii\helpers\Html;
use yii\helpers\Url;
?>
<div class="tf-section">
    <div class="wg-box">

        <div class="flex items-center justify-between mb-20">
            <h5 class="activity-title">
                <i class="icon-activity"></i> Активность
            </h5>
            <a class="tf-button style-1"
               href="<?= Url::to(['log/index']) ?>">
                <i class="icon-list me-1"></i> Все записи
            </a>
        </div>

        <?php /* ── Счётчики ── */ ?>
        <div class="stat-cards">
            <?php
            $cards = [
                [
                    'value' => $actionsToday,
                    'label' => 'Действий сегодня',
                    'icon'  => 'icon-edit-3',
                    'class' => 'stat-card--blue',
                ],
                [
                    'value' => $errorsToday,
                    'label' => 'Ошибок сегодня',
                    'icon'  => 'icon-alert-triangle',
                    'class' => $errorsToday > 0
                        ? 'stat-card--red'
                        : 'stat-card--green',
                ],
                [
                    'value' => $errorsWeek,
                    'label' => 'Ошибок за неделю',
                    'icon'  => 'icon-trending-up',
                    'class' => 'stat-card--gray',
                ],
            ];
            foreach ($cards as $c) : ?>
                <div class="stat-card <?= $c['class'] ?>">
                    <div class="stat-card__value">
                        <?= $c['value'] ?>
                    </div>
                    <div class="stat-card__label">
                        <i class="<?= $c['icon'] ?>"></i>
                        <?= $c['label'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php /* ── Лента ── */ ?>
        <div class="divider mb-14"></div>
        <div class="feed-heading">Последние действия</div>

        <?php if (empty($recentActions)) : ?>
            <div class="feed-empty">
                <i class="icon-inbox"></i>
                Действий пока нет
            </div>
        <?php else : ?>
            <div class="feed-list">
                <?php foreach ($recentActions as $log) :
                    $user = $log->user;
                    $userName = $user
                        ? Html::encode($user->name ?: $user->username)
                        : 'Система';
                    $modelUrl = $log->getModelUrl();
                    $modelLabel = Html::encode(
                        $log->model_label ?: ('ID: ' . $log->model_id)
                    );
                    $time = Yii::$app->formatter->asRelativeTime(
                        $log->created_at
                    );
                ?>
                <div class="feed-item">
                    <?php if ($user) : ?>
                        <?= Html::img($user->getAvatar(), [
                            'class' => 'feed-avatar',
                        ]) ?>
                    <?php else : ?>
                        <span class="feed-avatar feed-avatar--system">
                            <i class="icon-cpu"></i>
                        </span>
                    <?php endif; ?>

                    <div class="feed-body">
                        <span class="feed-user"><?= $userName ?></span>
                        <span class="feed-badge" style="<?= $log->getActionBadgeStyle() ?>">
                            <?= Html::encode($log->getActionLabel()) ?>
                        </span>
                        <?php if ($modelUrl) : ?>
                            <a href="<?= $modelUrl ?>"
                               class="feed-model"
                               title="<?= $modelLabel ?>">
                                <?= $modelLabel ?>
                            </a>
                        <?php else : ?>
                            <span class="feed-model"
                                  title="<?= $modelLabel ?>">
                                <?= $modelLabel ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <time class="feed-time"><?= $time ?></time>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
