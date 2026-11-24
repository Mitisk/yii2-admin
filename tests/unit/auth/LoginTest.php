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
 * Вход в админку: пароль, пароль + код из приложения, только код.
 */
final class LoginTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    public function testPasswordUserLogsInWithPassword(): void
    {
        $this->user('ivan', LoginForm::PASSWORD);

        $result = $this->post(['username' => 'ivan', 'password' => 'secret']);

        self::assertInstanceOf(Response::class, $result);
        self::assertFalse(Yii::$app->adminUser->isGuest);
    }

    public function testPasswordUserCannotLogInWithoutPassword(): void
    {
        $this->user('ivan', LoginForm::PASSWORD);

        $this->post(['username' => 'ivan', 'password' => '', 'mfaCode' => '123456']);

        self::assertTrue(Yii::$app->adminUser->isGuest, 'Вход без пароля');
    }

    public function testCodeOnlyUserCannotLogInWithoutCode(): void
    {
        $this->user('ivan', LoginForm::MFA);

        $this->post(['username' => 'ivan', 'password' => 'anything', 'mfaCode' => '']);

        self::assertTrue(Yii::$app->adminUser->isGuest, 'Вход без кода');
    }

    public function testCodeOnlyUserLogsInWithCode(): void
    {
        $this->user('ivan', LoginForm::MFA);

        $this->post(['username' => 'ivan', 'mfaCode' => Totp::code(self::SECRET)]);

        self::assertFalse(Yii::$app->adminUser->isGuest);
    }

    public function testCodeOnlyUserIsNotBrokenByAutofilledPassword(): void
    {
        $this->user('ivan', LoginForm::MFA);

        // Менеджер паролей подставил что-то в скрытое поле пароля
        $this->post(['username' => 'ivan', 'password' => 'autofill', 'mfaCode' => Totp::code(self::SECRET)]);

        self::assertFalse(Yii::$app->adminUser->isGuest);
    }

    public function testCodeOfAnotherAccountDoesNotUseSomeoneElsesPasswordStep(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);
        $this->user('petr', LoginForm::MFA)->updateAttributes(['mfa_secret' => 'KRSXG5CTMVRXEZLU']);
        $this->post(['username' => 'ivan', 'password' => 'secret']);

        $this->post(['username' => 'petr', 'mfaCode' => Totp::code('KRSXG5CTMVRXEZLU')]);

        self::assertSame('petr', Yii::$app->adminUser->identity->username);
    }

    public function testEmailWorksAsLogin(): void
    {
        $this->user('ivan', LoginForm::PASSWORD);

        $this->post(['username' => 'ivan@example.test', 'password' => 'secret']);

        self::assertFalse(Yii::$app->adminUser->isGuest);
        self::assertSame('ivan', Yii::$app->adminUser->identity->username);
    }

    public function testCodeOnlyUserStaysOnCodeStepAfterWrongCode(): void
    {
        $this->user('ivan', LoginForm::MFA);

        $html = $this->post(['username' => 'ivan', 'mfaCode' => Totp::wrongCode(self::SECRET)]);

        self::assertTrue(Yii::$app->adminUser->isGuest);
        self::assertIsString($html);
        self::assertStringContainsString('"step":"otp"', $html);
        self::assertStringContainsString('Неверный код', $html);
    }

    public function testCorrectPasswordOpensSeparateCodeStep(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);

        $result = $this->post(['username' => 'ivan', 'password' => 'secret']);

        self::assertInstanceOf(Response::class, $result, 'После пароля — переход на шаг кода');
        self::assertTrue(Yii::$app->adminUser->isGuest, 'Пароля мало: нужен код');

        $html = $this->page();
        self::assertStringContainsString('"step":"otp"', $html);
        self::assertStringNotContainsString('LoginForm[password]', $html, 'На шаге кода нет поля пароля');
        self::assertStringNotContainsString('LoginForm[username]', $html, 'На шаге кода нет поля логина');
        self::assertStringContainsString('LoginForm[mfaCode]', $html);
    }

    public function testWrongCodeKeepsCodeStepAndDoesNotAskPasswordAgain(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);
        $this->post(['username' => 'ivan', 'password' => 'secret']);

        $html = $this->post(['mfaCode' => Totp::wrongCode(self::SECRET)]);

        self::assertTrue(Yii::$app->adminUser->isGuest);
        self::assertIsString($html);
        self::assertStringContainsString('"step":"otp"', $html);
        self::assertStringContainsString('Неверный код', $html);
        self::assertStringNotContainsString('LoginForm[password]', $html);

        $this->post(['mfaCode' => Totp::code(self::SECRET)]);

        self::assertFalse(Yii::$app->adminUser->isGuest, 'Верный код после неверного — вход без повторного пароля');
        self::assertSame('ivan', Yii::$app->adminUser->identity->username);
    }

    public function testCodeWithoutPasswordStepIsRejected(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);

        $this->post(['username' => 'ivan', 'mfaCode' => Totp::code(self::SECRET)]);

        self::assertTrue(Yii::$app->adminUser->isGuest, 'Код без проверенного пароля');
    }

    public function testWrongPasswordDoesNotOpenCodeStep(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);

        $html = $this->post(['username' => 'ivan', 'password' => 'wrong']);

        self::assertIsString($html);
        self::assertStringContainsString('"step":"password"', $html);
        self::assertStringContainsString('Неверный логин или пароль', $html);

        $this->post(['mfaCode' => Totp::code(self::SECRET)]);
        self::assertTrue(Yii::$app->adminUser->isGuest);
    }

    public function testCodeStepExpires(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);
        $this->post(['username' => 'ivan', 'password' => 'secret']);

        $step = Yii::$app->session->get(LoginForm::MFA_STEP_SESSION_KEY);
        self::assertIsArray($step);
        $step['at'] = time() - LoginForm::MFA_STEP_TTL - 1;
        Yii::$app->session->set(LoginForm::MFA_STEP_SESSION_KEY, $step);

        $html = $this->post(['mfaCode' => Totp::code(self::SECRET)]);

        self::assertTrue(Yii::$app->adminUser->isGuest);
        self::assertIsString($html);
        self::assertStringContainsString('"step":"login"', $html);
    }

    public function testCodeStepClosesAfterTooManyWrongCodes(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);
        $this->post(['username' => 'ivan', 'password' => 'secret']);

        for ($i = 0; $i < LoginForm::MAX_ATTEMPTS; $i++) {
            $this->post(['mfaCode' => Totp::wrongCode(self::SECRET)]);
        }
        $this->post(['mfaCode' => Totp::code(self::SECRET)]);

        self::assertTrue(Yii::$app->adminUser->isGuest, 'Перебор кода остановлен');
    }

    public function testRestartLeavesCodeStep(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);
        $this->post(['username' => 'ivan', 'password' => 'secret']);

        $this->runAction('admin/default/login', [], ['restart' => '1']);

        self::assertStringContainsString('"step":"login"', $this->page());
        $this->post(['mfaCode' => Totp::code(self::SECRET)]);
        self::assertTrue(Yii::$app->adminUser->isGuest);
    }

    public function testNewPasswordAttemptReplacesCodeStepOfAnotherUser(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);
        $this->user('petr', LoginForm::PASSWORD);
        $this->post(['username' => 'ivan', 'password' => 'secret']);

        $this->post(['username' => 'petr', 'password' => 'secret']);

        self::assertSame('petr', Yii::$app->adminUser->identity->username);
        self::assertNull(Yii::$app->session->get(LoginForm::MFA_STEP_SESSION_KEY));
    }

    public function testTooManyFailuresBlockEvenCorrectPassword(): void
    {
        $this->user('ivan', LoginForm::PASSWORD);

        for ($i = 0; $i < LoginForm::MAX_ATTEMPTS; $i++) {
            $this->post(['username' => 'ivan', 'password' => 'wrong']);
        }
        $html = $this->post(['username' => 'ivan', 'password' => 'secret']);

        self::assertTrue(Yii::$app->adminUser->isGuest, 'Лимит попыток не действует');
        self::assertIsString($html);
        self::assertStringContainsString('лимит попыток', $html);
    }

    public function testBlockedUserCannotLogIn(): void
    {
        $this->user('ivan', LoginForm::PASSWORD)->updateAttributes(['status' => AdminUser::STATUS_BLOCKED]);
        $this->user('petr', LoginForm::MFA)->updateAttributes(['status' => AdminUser::STATUS_BLOCKED]);

        $this->post(['username' => 'ivan', 'password' => 'secret']);
        self::assertTrue(Yii::$app->adminUser->isGuest);

        $html = $this->post(['username' => 'petr', 'mfaCode' => Totp::code(self::SECRET)]);
        self::assertTrue(Yii::$app->adminUser->isGuest);
        self::assertIsString($html);
        self::assertStringContainsString('заблокирован', $html);
    }

    public function testCodeCannotBeUsedTwice(): void
    {
        $this->user('ivan', LoginForm::MFA);
        $code = Totp::code(self::SECRET);

        $this->post(['username' => 'ivan', 'mfaCode' => $code]);
        self::assertFalse(Yii::$app->adminUser->isGuest);
        Yii::$app->adminUser->logout(false);

        $this->post(['username' => 'ivan', 'mfaCode' => $code]);

        self::assertTrue(Yii::$app->adminUser->isGuest, 'Подсмотренный код сработал второй раз');
    }

    public function testUserWithoutSecretCannotLogInByCode(): void
    {
        $this->user('ivan', LoginForm::MFA)->updateAttributes(['mfa_secret' => null]);

        $html = $this->post(['username' => 'ivan', 'mfaCode' => '123456']);

        self::assertTrue(Yii::$app->adminUser->isGuest);
        self::assertIsString($html);
    }

    public function testArrayInputIsNotAnError(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);

        $html = $this->post(['username' => 'ivan', 'password' => ['a'], 'mfaCode' => ['1', '2']]);

        self::assertTrue(Yii::$app->adminUser->isGuest);
        self::assertIsString($html);
    }

    public function testCheckUserTellsOnlyWhetherPasswordIsAsked(): void
    {
        $this->user('ivan', LoginForm::MFA_PASSWORD);
        $this->user('petr', LoginForm::MFA);
        $this->user('oleg', LoginForm::PASSWORD)->updateAttributes(['status' => AdminUser::STATUS_BLOCKED]);

        self::assertSame('password', $this->checkUser('ivan')['type'], 'Про второй шаг узнают после пароля');
        self::assertSame('otp', $this->checkUser('petr')['type']);
        self::assertSame('password', $this->checkUser('oleg')['type'], 'Блокировку не раскрываем до проверки пароля');
        self::assertSame('password', $this->checkUser('nobody')['type']);
    }

    private function user(string $username, int $authType): AdminUser
    {
        $user = $this->createUser($username);
        $user->updateAttributes([
            'auth_type' => $authType,
            'mfa_secret' => $authType === LoginForm::PASSWORD ? null : self::SECRET,
        ]);

        return $user;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function post(array $form): mixed
    {
        return $this->runAction('admin/default/login', [], ['LoginForm' => $form]);
    }

    private function page(): string
    {
        $html = $this->runAction('admin/default/login');
        self::assertIsString($html);

        return $html;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkUser(string $username): array
    {
        $result = $this->runAction('admin/default/check-user', [], ['username' => $username]);
        self::assertIsArray($result);

        return $result;
    }
}
