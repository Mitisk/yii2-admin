<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\log;

use Mitisk\Yii2Admin\components\audit\AuditActions;
use Mitisk\Yii2Admin\components\audit\AuditPresenter;
use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\AuditLog;
use Mitisk\Yii2Admin\models\ContentBlock;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Журнал действий (/admin/log/): названия действий и разделов, читаемые изменения, фильтры,
 * очистка, доступ к логу приложения.
 */
final class AuditJournalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AuditLog::deleteAll();
    }

    public function testProjectActionIsShownByItsNameAndOfferedInFilter(): void
    {
        Yii::$app->getModule('admin')->auditActions = [
            'block' => ['label' => 'заблокировал', 'tone' => 'red'],
            'awards' => 'изменил награды',
        ];
        $this->log(['action' => 'block', 'model_label' => 'Иван']);
        $this->log(['action' => 'awards', 'model_label' => 'Иван']);
        $this->log(['action' => 'mystery', 'model_label' => 'Иван']);

        $html = $this->page();

        self::assertStringContainsString('lg-chip lg-chip--red">заблокировал<', $html);
        self::assertStringContainsString('lg-chip lg-chip--gray">изменил награды<', $html);
        self::assertStringContainsString('<option value="block">Заблокировал</option>', $html);
        // Действие без названия видно кодом, а подробности подсказывают, где его назвать
        self::assertStringContainsString('lg-chip lg-chip--gray">mystery<', $html);
        self::assertStringContainsString('auditActions', $html);
    }

    public function testBuiltInActionsNeedNoConfig(): void
    {
        $actions = new AuditActions(['bad' => '', 5 => 'число вместо кода', 'tone' => ['label' => 'x', 'tone' => 'pink']]);

        self::assertSame('изменил', $actions->label('update'));
        self::assertSame('red', $actions->tone('delete'));
        self::assertFalse($actions->isKnown('bad'), 'Пустое название — действия нет');
        self::assertSame('gray', $actions->tone('tone'), 'Незнакомый цвет — серый');
        self::assertSame(['update' => 'Изменил', 'create' => 'Создал'], $actions->options(['create', 'update', '']), 'По алфавиту названий');
    }

    public function testSectionNameComesFromComponentByClassTableOrBuiltIn(): void
    {
        $this->registerNoteComponent();
        $presenter = new AuditPresenter();

        self::assertSame('Заметки', $presenter->section(Note::class));
        self::assertSame('Заметки', $presenter->section(NoteTwin::class), 'Другой класс той же таблицы — тот же раздел');
        self::assertSame('Текстовые блоки', $presenter->section(ContentBlock::class));
        self::assertSame('Gone', $presenter->section('app\models\Gone'), 'Класса нет — его короткое имя');

        $log = new AuditLog(['action' => 'block', 'model_class' => NoteTwin::class, 'model_id' => '7']);
        self::assertSame('/admin/test-notes/view/?id=7', $presenter->url($log));
        $log->action = 'delete';
        self::assertNull($presenter->url($log), 'У удалённой записи ссылки нет');
        $block = new AuditLog(['action' => 'update', 'model_class' => ContentBlock::class, 'model_id' => '85']);
        self::assertSame('/admin/content-block/update/?id=85', $presenter->url($block));
    }

    public function testChangesAreShownWithFieldLabelsAndReadableValues(): void
    {
        $this->registerNoteComponent();
        $stamp = strtotime('2026-11-10 13:41:00');
        $log = new AuditLog([
            'action' => 'update',
            'model_class' => Note::class,
            'model_id' => '1',
            'diff' => json_encode([
                'title' => ['Старая', 'Новая'],
                'published' => [0, 1],
                'updated_at' => [null, $stamp],
                'password_hash' => ['$2y$13$old', '$2y$13$new'],
                'tags' => [null, ['a', 'b']],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $changes = [];
        foreach ((new AuditPresenter())->changes($log) as $row) {
            $changes[$row['attribute']] = $row;
        }

        self::assertSame('Заголовок', $changes['title']['field'], 'Подпись — из формы раздела');
        self::assertSame('Нет', $changes['published']['old']['text']);
        self::assertSame('Да', $changes['published']['new']['text']);
        self::assertTrue($changes['updated_at']['old']['empty']);
        self::assertSame('10.11.2026 13:41', $changes['updated_at']['new']['text']);
        self::assertSame('скрыто', $changes['password_hash']['new']['text']);
        self::assertStringContainsString('"a"', $changes['tags']['new']['text']);
    }

    public function testSecretsAreNotWrittenToTheJournal(): void
    {
        $this->loginAs(self::ADMIN_ID);
        Yii::$app->set('user', Yii::$app->get('adminUser'));
        $user = $this->createUser('journal-secret');
        $oldHash = $user->password_hash;
        $user->password_hash = 'new-hash-value';
        $user->email = 'changed@example.test';

        AuditService::log('update', $user, ['password_hash' => $oldHash, 'email' => 'journal-secret@example.test']);

        $diff = AuditLog::find()->orderBy(['id' => SORT_DESC])->one()->getDiffArray();
        self::assertSame([AuditService::HIDDEN_VALUE, AuditService::HIDDEN_VALUE], $diff['password_hash']);
        self::assertSame(['journal-secret@example.test', 'changed@example.test'], $diff['email']);
    }

    public function testFiltersNarrowTheFeed(): void
    {
        $this->log(['action' => 'update', 'model_label' => 'Телефон в шапке', 'diff' => '{"value":["+7 495 000","+7 495 123-45-67"]}']);
        $this->log(['action' => 'delete', 'model_label' => 'Старая акция', 'model_class' => Note::class, 'created_at' => time() - 40 * 86400]);
        $this->log(['action' => 'create', 'model_label' => 'Новая акция', 'model_class' => Note::class, 'user_id' => null]);

        self::assertSame(['Телефон в шапке'], $this->labels(['search' => '123-45-67']), 'Поиск по изменённому значению');
        self::assertSame(['Старая акция'], $this->labels(['action' => 'delete']));
        self::assertSame(['Новая акция', 'Старая акция'], $this->labels(['section' => Note::class]));
        self::assertSame(['Новая акция', 'Телефон в шапке'], $this->labels(['period' => '30']));
        self::assertSame(['Новая акция'], $this->labels(['user_id' => 'system']));
        self::assertSame(['Старая акция'], $this->labels(['period' => 'today', 'date_to' => date('Y-m-d', time() - 30 * 86400)]), 'Свои даты важнее периода');

        $html = $this->page(['search' => 'нет такой записи']);
        self::assertStringContainsString('Ничего не найдено', $html);
    }

    public function testFeedIsGroupedByDayAndShowsWhoAndFromWhere(): void
    {
        $this->log(['model_label' => 'Сегодняшняя', 'ip' => '10.1.2.3', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36']);
        $this->log(['model_label' => 'Вчерашняя', 'created_at' => strtotime('yesterday 12:00')]);

        $html = $this->page();

        self::assertStringContainsString('<h2 class="lg-day">Сегодня</h2>', $html);
        self::assertStringContainsString('<h2 class="lg-day">Вчера</h2>', $html);
        self::assertStringContainsString('10.1.2.3', $html);
        self::assertStringContainsString('Chrome, Windows', $html);
    }

    public function testClearRemovesOnlyOldRecordsAndLeavesATrace(): void
    {
        $this->log(['model_label' => 'Свежая']);
        $this->log(['model_label' => 'Древняя', 'created_at' => time() - 100 * 86400]);
        $this->loginAs(self::ADMIN_ID);

        $this->runAction('admin/log/clear-audit', [], ['keep_days' => 90]);

        $labels = AuditLog::find()->select('model_label')->orderBy(['id' => SORT_ASC])->column();
        self::assertSame('Свежая', $labels[0]);
        self::assertCount(2, $labels);
        self::assertStringContainsString('старше 90 дн. — 1 шт.', $labels[1]);
        $trace = AuditLog::findOne(['action' => AuditActions::CLEAR]);
        self::assertSame(self::ADMIN_ID, (int) $trace->user_id);
    }

    public function testClearNeedsSuperAdminAndAKnownTerm(): void
    {
        $this->log(['model_label' => 'Запись']);
        $viewer = $this->createUser('journal-viewer', ['viewReports']);
        $this->loginAs($viewer->id);
        $this->runAction('admin/log/clear-audit', [], ['keep_days' => 0]);
        self::assertSame(1, (int) AuditLog::find()->count(), 'Право смотреть журнал не даёт его чистить');

        $this->loginAs(self::ADMIN_ID);
        $this->runAction('admin/log/clear-audit', [], ['keep_days' => 7]);
        self::assertSame(1, (int) AuditLog::find()->count(), 'Срока 7 дней в списке нет — ничего не удаляется');
    }

    public function testApplicationLogIsForAdminOnly(): void
    {
        $viewer = $this->createUser('journal-viewer', ['viewReports']);
        $this->loginAs($viewer->id);

        self::assertStringNotContainsString('data-lg-pane="system"', $this->page(), 'Вкладки «Ошибки сайта» у наблюдателя нет');

        $this->expectException(ForbiddenHttpException::class);
        $this->runAction('admin/log/system-log');
    }

    public function testApplicationLogComesGroupedWithSummaryAndWithoutSecrets(): void
    {
        $file = Yii::getAlias('@runtime/logs/app.log');
        @mkdir(dirname($file), 0777, true);
        $now = date('Y-m-d H:i:s');
        file_put_contents($file, implode("\n", [
            "{$now} [10.0.0.1][1][-][error][yii\\web\\HttpException:404] yii\\web\\NotFoundHttpException: Страница не найдена in /x/Y.php:10",
            "{$now} [10.0.0.1][1][-][error][yii\\web\\HttpException:404] yii\\web\\NotFoundHttpException: Страница не найдена in /x/Y.php:10",
            "{$now} [10.0.0.1][1][-][error][app] Error: Сломалось in /x/Z.php:5",
            "{$now} [10.0.0.1][1][-][warning][yii\\web\\User::renewAuthStatus] Invalid session auth key attempted for user '1' from 10.0.0.1: 18Z3hxhK_2ProhYy3iMpOP78ieTwNAHz",
        ]) . "\n");
        $this->loginAs(self::ADMIN_ID);

        try {
            $groups = $this->runAction('admin/log/system-log');
            $quiet = $this->runAction('admin/log/system-log', ['hide_noise' => '1', 'level' => 'error']);
            $list = $this->runAction('admin/log/system-log', ['view' => 'list', 'search' => 'Сломалось']);
        } finally {
            @unlink($file);
        }

        self::assertSame(Response::FORMAT_JSON, Yii::$app->response->format);
        self::assertSame(3, $groups['total']);
        self::assertSame(4, $groups['matched']);
        self::assertSame(1, $groups['summary']['errorsToday'], 'Ошибки посетителей в «ошибки сайта» не входят');
        self::assertSame(1, $groups['summary']['warningsToday']);
        self::assertSame(2, $groups['summary']['noise']);
        $counts = array_column($groups['items'], 'count', 'title');
        self::assertSame(2, $counts['Страница не найдена']);
        self::assertStringStartsWith('сегодня ', $groups['items'][0]['last']);
        self::assertSame('admin', $groups['items'][0]['cases'][0]['userName'] !== '' ? 'admin' : '', 'Имя пользователя подставлено по id');
        self::assertStringNotContainsString('18Z3hxhK_2ProhYy3iMpOP78ieTwNAHz', json_encode($groups, JSON_UNESCAPED_UNICODE));

        self::assertSame(['Сломалось'], array_column($quiet['items'], 'title'));
        self::assertSame('list', $list['view']);
        self::assertSame(['Сломалось'], array_column($list['items'], 'title'));
        self::assertSame('10.0.0.1', $list['items'][0]['ip']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function log(array $attributes = []): AuditLog
    {
        $log = new AuditLog();
        $log->setAttributes($attributes + [
            'user_id' => self::ADMIN_ID,
            'action' => 'update',
            'model_class' => ContentBlock::class,
            'model_id' => '1',
            'model_label' => 'Запись',
            'created_at' => time(),
        ], false);
        self::assertTrue($log->save(false));

        return $log;
    }

    /**
     * @param array<string, string> $query
     */
    private function page(array $query = []): string
    {
        if (Yii::$app->adminUser->getIdentity(false) === null) {
            $this->loginAs(self::ADMIN_ID);
        }

        return (string) $this->runAction('admin/log/index', $query);
    }

    /**
     * Названия записей в ленте, по алфавиту.
     *
     * @param array<string, string> $query
     * @return string[]
     */
    private function labels(array $query): array
    {
        preg_match_all('/class="lg-record"[^>]*>([^<]+)</u', $this->page($query), $m);
        $labels = array_map('html_entity_decode', $m[1]);
        sort($labels);

        return $labels;
    }
}

/**
 * Модель проекта на той же таблице, что и раздел админки (раздел заведён на другой класс).
 */
final class NoteTwin extends \yii\db\ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%test_note}}';
    }
}
