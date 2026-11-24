<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests;

use Mitisk\Yii2Admin\models\AdminModel;
use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\tests\_app\models\Note;
use Yii;

/**
 * Базовый тест: свежее веб-приложение с модулем admin и транзакция, которая откатывается после теста.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** Администратор из сида модуля (роль superAdminRole). */
    protected const ADMIN_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        TestApp::web();
        // Кэш настроек — статический: откат транзакции прошлого теста его не сбрасывает
        \Mitisk\Yii2Admin\models\Settings::clearCache();
        Yii::$app->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $transaction = Yii::$app->db->getTransaction();
        if ($transaction !== null && $transaction->getIsActive()) {
            $transaction->rollBack();
        }
        TestApp::destroy();
        parent::tearDown();
    }

    protected function loginAs(int $userId): AdminUser
    {
        $user = AdminUser::findOne($userId);
        self::assertNotNull($user, "Нет пользователя #{$userId}");
        Yii::$app->adminUser->setIdentity($user);

        return $user;
    }

    /**
     * Активный пользователь админки только с перечисленными разрешениями.
     *
     * @param string[] $permissions Имена разрешений, например Note::class . '\update'
     */
    protected function createUser(string $username, array $permissions = []): AdminUser
    {
        $now = time();
        Yii::$app->db->createCommand()->insert(AdminUser::tableName(), [
            'username' => $username,
            'email' => $username . '@example.test',
            'password_hash' => Yii::$app->security->generatePasswordHash('secret', 4),
            'auth_key' => Yii::$app->security->generateRandomString(),
            'status' => AdminUser::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
        $id = (int) Yii::$app->db->getLastInsertID();

        $auth = Yii::$app->authManager;
        foreach ($permissions as $name) {
            $permission = $auth->getPermission($name);
            if ($permission === null) {
                $permission = $auth->createPermission($name);
                $auth->add($permission);
            }
            $auth->assign($permission, $id);
        }

        $user = AdminUser::findOne($id);
        self::assertNotNull($user);

        return $user;
    }

    /**
     * Регистрирует «Заметки» компонентом админки (alias test-notes).
     *
     * @param array<string, mixed>|null $adminActions Значение ключа admin_actions в list; null — ключа нет
     * @param array<int, array<string, mixed>>|null $data Поля формы; null — набор по умолчанию
     */
    protected function registerNoteComponent(?array $adminActions = null, ?array $data = null): AdminModel
    {
        $list = [
            'title' => ['on' => '1'],
            'published' => ['on' => '1'],
            'archived' => ['on' => '1'],
        ];
        if ($adminActions !== null) {
            $list['admin_actions'] = $adminActions;
        }

        $data ??= [
            ['name' => 'title', 'label' => 'Заголовок', 'type' => 'text'],
            ['name' => 'published', 'label' => 'Опубликована', 'type' => 'posted'],
            ['name' => 'archived', 'label' => 'В архиве', 'type' => 'posted', 'readonly' => true],
            ['name' => 'tags', 'label' => 'Теги', 'type' => 'textarea'],
        ];

        $component = AdminModel::findOne(['table_name' => 'test_note']) ?? new AdminModel();
        $component->setAttributes([
            'name' => 'Заметки',
            'alias' => 'test-notes',
            'table_name' => 'test_note',
            'model_class' => Note::class,
            'admin_label' => 'title',
            'view' => 1,
            'in_menu' => 0,
            'can_create' => 1,
            'list' => $list,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ], false);
        self::assertTrue($component->save(), print_r($component->getErrors(), true));

        return $component;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function createNote(array $attributes = []): Note
    {
        $note = new Note();
        $note->setAttributes($attributes + ['title' => 'Заметка', 'published' => 0, 'archived' => 0], false);
        self::assertTrue($note->save(false));

        return $note;
    }

    /**
     * Выполняет экшен как HTTP-запрос: GET-параметры уходят и в query, и в параметры экшена.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $post null — GET-запрос, массив (и пустой) — POST
     */
    protected function runAction(string $route, array $query = [], ?array $post = null): mixed
    {
        $_SERVER['REQUEST_METHOD'] = $post === null ? 'GET' : 'POST';
        $request = Yii::$app->request;
        $request->setQueryParams($query);
        $request->setBodyParams($post ?? []);

        return Yii::$app->runAction($route, $query);
    }
}
