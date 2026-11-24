<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\unit\auth;

use Mitisk\Yii2Admin\models\AdminUser;
use Mitisk\Yii2Admin\models\LoginForm;
use Mitisk\Yii2Admin\tests\_support\Totp;
use Mitisk\Yii2Admin\tests\TestCase;
use Yii;
use yii\web\Response;

/**
 * Карточка пользователя: способ входа и подключение приложения-аутентификатора.
 */
final class UserMfaFormTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs(self::ADMIN_ID);
    }

    public function testStoredSecretIsNeverShownAgain(): void
    {
        $user = $this->user(LoginForm::MFA_PASSWORD);

        $html = $this->form($user);

        self::assertStringNotContainsString(self::SECRET, $html, 'Действующий секрет попал в страницу');
        self::assertStringContainsString('Приложение подключено', $html);
    }

    public function testQrIsNotRequestedFromExternalService(): void
    {
        $html = $this->form($this->user(LoginForm::PASSWORD));

        self::assertStringNotContainsString('quickchart', $html);
        self::assertStringNotContainsString('otpauth://', $html, 'Ссылку с секретом собирает браузер, в разметке её нет');
    }

    public function testCodeLoginIsNotEnabledWithoutConfirmation(): void
    {
        $user = $this->user(LoginForm::PASSWORD);
        $this->form($user);

        $html = $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD]);

        self::assertIsString($html, 'Без кода форма не сохраняется');
        self::assertStringContainsString('Введите код из приложения', $html);
        $user->refresh();
        self::assertSame(LoginForm::PASSWORD, (int) $user->auth_type);
        self::assertNull($user->mfa_secret);
    }

    public function testWrongConfirmationCodeIsRejected(): void
    {
        $user = $this->user(LoginForm::PASSWORD);
        $secret = $this->pendingSecret($this->form($user));

        $html = $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD, 'mfa_code' => Totp::wrongCode($secret)]);

        self::assertIsString($html);
        self::assertStringContainsString('Код не подошёл', $html);
        self::assertSame($secret, $this->pendingSecret($html), 'После ошибки QR-код тот же: приложение перенастраивать не нужно');
        $user->refresh();
        self::assertNull($user->mfa_secret);
    }

    public function testConfirmedCodeEnablesCodeLogin(): void
    {
        $user = $this->user(LoginForm::PASSWORD);
        $secret = $this->pendingSecret($this->form($user));

        $result = $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD, 'mfa_code' => Totp::code($secret)]);

        self::assertInstanceOf(Response::class, $result);
        $user->refresh();
        self::assertSame(LoginForm::MFA_PASSWORD, (int) $user->auth_type);
        self::assertSame($secret, $user->mfa_secret);
    }

    public function testSecretFromRequestIsIgnored(): void
    {
        $user = $this->user(LoginForm::PASSWORD);
        $this->form($user);
        $own = 'KRSXG5CTMVRXEZLU';

        $this->save($user, [
            'auth_type' => LoginForm::MFA_PASSWORD,
            'mfa_secret' => $own,
            'mfa_code' => Totp::code($own),
        ]);

        $user->refresh();
        self::assertNull($user->mfa_secret, 'Секрет подставлен из запроса');
        self::assertSame(LoginForm::PASSWORD, (int) $user->auth_type);
    }

    public function testConnectedUserIsSavedWithoutCode(): void
    {
        $user = $this->user(LoginForm::MFA_PASSWORD);

        $result = $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD, 'name' => 'Иван Петров']);

        self::assertInstanceOf(Response::class, $result);
        $user->refresh();
        self::assertSame('Иван Петров', $user->name);
        self::assertSame(self::SECRET, $user->mfa_secret);
    }

    public function testConnectedUserSwitchesBetweenCodeModesWithoutNewSetup(): void
    {
        $user = $this->user(LoginForm::MFA_PASSWORD);

        $result = $this->save($user, ['auth_type' => LoginForm::MFA]);

        self::assertInstanceOf(Response::class, $result);
        $user->refresh();
        self::assertSame(LoginForm::MFA, (int) $user->auth_type);
        self::assertSame(self::SECRET, $user->mfa_secret);
    }

    public function testReconnectKeepsOldAppUntilNewOneIsConfirmed(): void
    {
        $user = $this->user(LoginForm::MFA_PASSWORD);
        $secret = $this->pendingSecret($this->form($user));

        $html = $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD, 'mfa_reset' => '1']);

        self::assertIsString($html);
        $user->refresh();
        self::assertSame(self::SECRET, $user->mfa_secret, 'Старое приложение отключено без подтверждения нового');

        $result = $this->save($user, [
            'auth_type' => LoginForm::MFA_PASSWORD,
            'mfa_reset' => '1',
            'mfa_code' => Totp::code($secret),
        ]);

        self::assertInstanceOf(Response::class, $result);
        $user->refresh();
        self::assertSame($secret, $user->mfa_secret);
        self::assertNotSame(self::SECRET, $secret);
    }

    public function testPasswordOnlyRemovesSecret(): void
    {
        $user = $this->user(LoginForm::MFA_PASSWORD);

        $this->save($user, ['auth_type' => LoginForm::PASSWORD]);

        $user->refresh();
        self::assertSame(LoginForm::PASSWORD, (int) $user->auth_type);
        self::assertNull($user->mfa_secret);
    }

    public function testNextSetupGetsNewSecret(): void
    {
        $user = $this->user(LoginForm::PASSWORD);
        $first = $this->pendingSecret($this->form($user));
        $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD, 'mfa_code' => Totp::code($first)]);
        $this->save($user, ['auth_type' => LoginForm::PASSWORD]);

        $second = $this->pendingSecret($this->form($user));

        self::assertNotSame($first, $second);
    }

    public function testSetupOfOneUserDoesNotFitAnother(): void
    {
        $ivan = $this->user(LoginForm::PASSWORD, 'ivan');
        $petr = $this->user(LoginForm::PASSWORD, 'petr');
        $ivanSecret = $this->pendingSecret($this->form($ivan));

        $this->save($petr, ['auth_type' => LoginForm::MFA_PASSWORD, 'mfa_code' => Totp::code($ivanSecret)]);

        $petr->refresh();
        self::assertNull($petr->mfa_secret);
    }

    public function testCodeOnlyUserIsCreatedWithoutPassword(): void
    {
        $secret = $this->pendingSecret($this->runCreate());

        $result = $this->runCreate([
            'username' => 'newcomer',
            'email' => 'newcomer@example.test',
            'status' => AdminUser::STATUS_ACTIVE,
            'auth_type' => LoginForm::MFA,
            'mfa_code' => Totp::code($secret),
        ]);

        self::assertInstanceOf(Response::class, $result);
        $user = AdminUser::findOne(['username' => 'newcomer']);
        self::assertNotNull($user);
        self::assertSame($secret, $user->mfa_secret);
        self::assertNotSame('', (string) $user->password_hash);
    }

    public function testPasswordUserIsNotCreatedWithoutPassword(): void
    {
        $html = $this->runCreate([
            'username' => 'newcomer',
            'email' => 'newcomer@example.test',
            'status' => AdminUser::STATUS_ACTIVE,
            'auth_type' => LoginForm::PASSWORD,
        ]);

        self::assertIsString($html);
        self::assertNull(AdminUser::findOne(['username' => 'newcomer']));
    }

    public function testLeavingCodeOnlyModeRequiresNewPassword(): void
    {
        $user = $this->user(LoginForm::MFA);

        $html = $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD]);

        self::assertIsString($html, 'У входа «только код» пароля нет: без нового пароля человек не войдёт');
        self::assertStringContainsString('Задайте пароль', $html);
        $user->refresh();
        self::assertSame(LoginForm::MFA, (int) $user->auth_type);

        $result = $this->save($user, ['auth_type' => LoginForm::MFA_PASSWORD, 'password' => 'new-password']);

        self::assertInstanceOf(Response::class, $result);
        $user->refresh();
        self::assertTrue($user->validatePassword('new-password'));
    }

    public function testSwitchingToCodeOnlyDisablesOldPassword(): void
    {
        $user = $this->user(LoginForm::MFA_PASSWORD);

        $this->save($user, ['auth_type' => LoginForm::MFA]);

        $user->refresh();
        self::assertFalse($user->validatePassword('secret'));
    }

    public function testNewUserIsActiveByDefault(): void
    {
        $html = $this->runCreate();

        self::assertIsString($html);
        self::assertMatchesRegularExpression(
            '/<option value="' . AdminUser::STATUS_ACTIVE . '" selected[^>]*>Активен/u',
            $html,
            'В форме создания по умолчанию выбран статус «Заблокирован»'
        );
    }

    public function testAvatarPlaceholderIsNotSavedAsAvatar(): void
    {
        $this->runCreate([
            'username' => 'newcomer',
            'email' => 'newcomer@example.test',
            'status' => AdminUser::STATUS_ACTIVE,
            'auth_type' => LoginForm::PASSWORD,
            'password' => 'some-password',
        ], ['fileuploader-list-files' => '[{"file":"/assets/abc123/img/no_avatar.png"}]']);

        $user = AdminUser::findOne(['username' => 'newcomer']);
        self::assertNotNull($user);
        self::assertSame('', (string) $user->image);
    }

    public function testAvatarPathAndPasswordHashAreNotTakenFromRequest(): void
    {
        $user = $this->user(LoginForm::PASSWORD);
        $hash = $user->password_hash;

        $this->save($user, [
            'auth_type' => LoginForm::PASSWORD,
            'image' => '/web/users/avatar/../../../config/db.php',
            'password_hash' => 'posted-hash',
        ]);

        $user->refresh();
        self::assertSame('', (string) $user->image, 'Путь аватара из запроса: позже по нему удаляется файл');
        self::assertSame($hash, $user->password_hash);
    }

    private function user(int $authType, string $username = 'ivan'): AdminUser
    {
        $user = $this->createUser($username);
        $user->updateAttributes([
            'auth_type' => $authType,
            'mfa_secret' => $authType === LoginForm::PASSWORD ? null : self::SECRET,
        ]);

        return $user;
    }

    private function form(AdminUser $user): string
    {
        $html = $this->runAction('admin/user/update', ['id' => $user->id]);
        self::assertIsString($html);

        return $html;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function save(AdminUser $user, array $attributes): mixed
    {
        return $this->runAction('admin/user/update', ['id' => $user->id], [
            'AdminUser' => $attributes + [
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * @param array<string, mixed>|null $attributes null — открыть форму
     * @param array<string, mixed> $extra
     */
    private function runCreate(?array $attributes = null, array $extra = []): mixed
    {
        return $this->runAction(
            'admin/user/create',
            [],
            $attributes === null ? null : ['AdminUser' => $attributes] + $extra
        );
    }

    /** Секрет, который форма предлагает подключить (из него браузер рисует QR-код). */
    private function pendingSecret(mixed $html): string
    {
        self::assertIsString($html);
        self::assertSame(1, preg_match('/data-mfa-secret="([A-Z2-7]{16,})"/', $html, $match), 'В форме нет секрета для подключения');

        return $match[1];
    }
}
