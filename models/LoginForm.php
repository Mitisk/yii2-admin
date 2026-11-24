<?php

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\MfaHelper;
use Yii;
use yii\base\Model;

/**
 * Вход в админку по шагам: логин → пароль → код из приложения.
 *
 * Три типа входа (user.auth_type): только пароль, пароль и код, только код. При «пароль и код»
 * пароль проверяется первым запросом; верный пароль запоминается в сессии на {@see MFA_STEP_TTL}
 * секунд (id пользователя, а не сам пароль), и второй запрос несёт только код. Неверный код
 * оставляет человека на шаге кода — пароль заново не спрашивается.
 *
 * Пустое значение никогда не считается пройденной проверкой: каждый шаг требует свой фактор явно.
 *
 * @property-read AdminUser|null $user
 */
class LoginForm extends Model
{
    /** Только пароль */
    public const PASSWORD = 0;
    /** Пароль и временный код */
    public const MFA_PASSWORD = 1;
    /** Только временный код */
    public const MFA = 2;

    /** Неудачных попыток с одного адреса до паузы */
    public const MAX_ATTEMPTS = 5;
    /** Пауза после исчерпания попыток, секунды */
    public const BLOCK_DURATION = 1800;
    /** Неверных кодов на одного пользователя (с любых адресов) до паузы */
    public const MAX_CODE_FAILURES = 10;

    /** Ключ сессии: пароль проверен, ждём код */
    public const MFA_STEP_SESSION_KEY = '__admin_mfa_step';
    /** Сколько секунд после верного пароля можно вводить код */
    public const MFA_STEP_TTL = 300;

    public const STEP_LOGIN = 'login';
    public const STEP_PASSWORD = 'password';
    public const STEP_CODE = 'otp';

    /** Вход выполнен */
    public const RESULT_DONE = 'done';
    /** Пароль верный, нужен код: показать шаг кода */
    public const RESULT_CODE_STEP = 'code';
    /** Не вышло: показать форму с ошибкой на шаге {@see $step} */
    public const RESULT_RETRY = 'retry';

    /** @var string|null Логин или email */
    public $username;
    /** @var string|null Пароль */
    public $password;
    /** @var string|null Шесть цифр из приложения */
    public $mfaCode;
    /** @var int Тип входа найденного пользователя */
    public int $authType = self::PASSWORD;
    /** @var bool Запомнить меня */
    public bool $rememberMe = true;
    /** @var string Шаг, который показывает форма */
    public string $step = self::STEP_LOGIN;
    /** @var bool Шаг кода открыт верным паролем: в форме нет полей логина и пароля */
    public bool $passwordConfirmed = false;

    private ?AdminUser $_user = null;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // Проверки — в attempt(): правила нужны только для load()
            [['username', 'password', 'mfaCode'], 'safe'],
            ['rememberMe', 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'username' => 'Логин',
            'password' => 'Пароль',
            'mfaCode' => 'Одноразовый код',
            'rememberMe' => 'Запомнить меня',
        ];
    }

    /**
     * Обрабатывает отправленную форму.
     *
     * @return string одна из констант RESULT_*
     */
    public function attempt(): string
    {
        $this->normalize();
        $this->clearErrors();

        // У аккаунта «только код» пароль не участвует: менеджер паролей мог подставить его в скрытое поле
        $codeOnlyAccount = $this->mfaCode !== ''
            && $this->username !== ''
            && $this->stepFor($this->getUser()) === self::STEP_CODE;

        if ($this->password !== '' && !$codeOnlyAccount) {
            return $this->attemptPassword();
        }
        if ($this->mfaCode !== '') {
            return $this->attemptCode();
        }

        // Пришёл только логин (Enter в поле): показываем следующий шаг без ошибки
        $this->forgetCodeStep();
        $this->step = $this->username === '' ? self::STEP_LOGIN : $this->stepFor($this->getUser());

        return self::RESULT_RETRY;
    }

    /**
     * Совместимость: «проверить и войти» одним вызовом.
     */
    public function login(): bool
    {
        return $this->attempt() === self::RESULT_DONE;
    }

    /**
     * Состояние формы при открытии страницы: если пароль уже проверен — сразу шаг кода.
     */
    public function restoreStep(): void
    {
        $user = $this->codeStepUser();
        if ($user !== null) {
            $this->showCodeStepOf($user);
        }
    }

    /**
     * «Войти под другим логином»: забыть проверенный пароль.
     */
    public function forgetCodeStep(): void
    {
        Yii::$app->session->remove(self::MFA_STEP_SESSION_KEY);
    }

    /**
     * Какой шаг идёт после логина. Про второй фактор при «пароль и код» здесь не сообщается:
     * о нём узнают только после верного пароля.
     */
    public function stepFor(?AdminUser $user): string
    {
        return $user !== null && (int) $user->auth_type === self::MFA ? self::STEP_CODE : self::STEP_PASSWORD;
    }

    /**
     * Пользователь по логину или email.
     */
    public function findUser(?string $login): ?AdminUser
    {
        $login = trim((string) $login);
        if ($login === '') {
            return null;
        }

        return AdminUser::find()
            ->where(['or', ['username' => $login], ['email' => $login]])
            ->one();
    }

    /**
     * @return AdminUser|null
     */
    public function getUser()
    {
        if ($this->_user === null) {
            $this->_user = $this->findUser(is_string($this->username) ? $this->username : null);
            if ($this->_user !== null) {
                $this->authType = (int) $this->_user->auth_type;
            }
        }

        return $this->_user;
    }

    /**
     * Тип входа по логину (для совместимости со старым кодом).
     */
    public function getAuthTypeByUsername(): int
    {
        $user = $this->getUser();

        return $user !== null ? (int) $user->auth_type : self::PASSWORD;
    }

    // --- Шаги ---

    private function attemptPassword(): string
    {
        // Новый пароль — новая попытка: прежний незаконченный шаг кода не действует
        $this->forgetCodeStep();
        $this->step = $this->username === '' ? self::STEP_LOGIN : self::STEP_PASSWORD;

        if ($this->failIfThrottled('password')) {
            return self::RESULT_RETRY;
        }

        $user = $this->getUser();
        $passwordFits = $user !== null
            && (int) $user->auth_type !== self::MFA
            && (string) $user->password_hash !== ''
            && $user->validatePassword($this->password);

        if (!$passwordFits) {
            $this->registerAttempt();
            $this->addError('password', 'Неверный логин или пароль.');
            return self::RESULT_RETRY;
        }
        if ((int) $user->status !== AdminUser::STATUS_ACTIVE) {
            $this->addError('username', 'Ваш аккаунт заблокирован.');
            return self::RESULT_RETRY;
        }

        if ((int) $user->auth_type === self::PASSWORD) {
            return $this->finish($user, $this->rememberMe);
        }

        if ((string) $user->mfa_secret === '') {
            $this->addError('mfaCode', 'Вход по коду для этого аккаунта не настроен. Обратитесь к администратору.');
            return self::RESULT_RETRY;
        }

        Yii::$app->session->set(self::MFA_STEP_SESSION_KEY, [
            'id' => (int) $user->id,
            'at' => time(),
            'attempts' => 0,
            'remember' => $this->rememberMe,
        ]);
        $this->showCodeStepOf($user);

        return self::RESULT_CODE_STEP;
    }

    private function attemptCode(): string
    {
        $pendingUser = $this->codeStepUser();
        // Шаг кода шлёт только код. Если пришёл ещё и логин другого аккаунта — это новая попытка
        if ($pendingUser !== null && $this->username !== '' && !$this->isLoginOf($pendingUser)) {
            $this->forgetCodeStep();
            $pendingUser = null;
        }

        if ($pendingUser !== null) {
            $user = $pendingUser;
            $this->showCodeStepOf($user);
        } else {
            // Без проверенного пароля код принимается только у аккаунтов «только код»
            $user = $this->getUser();
            if ($user === null || (int) $user->auth_type !== self::MFA) {
                $this->step = self::STEP_LOGIN;
                $this->addError('mfaCode', 'Время на ввод кода вышло. Войдите заново.');
                return self::RESULT_RETRY;
            }
            $this->step = self::STEP_CODE;
        }

        if ($this->failIfThrottled('mfaCode')) {
            return self::RESULT_RETRY;
        }
        if ($this->codeFailures($user) >= self::MAX_CODE_FAILURES) {
            $this->addError('mfaCode', 'Слишком много неверных кодов для этого аккаунта. Попробуйте через полчаса.');
            return self::RESULT_RETRY;
        }

        if (!$this->codeFits($user)) {
            $this->registerAttempt();
            $this->registerCodeFailure($user);
            if ($pendingUser !== null && $this->countCodeStepAttempt() >= self::MAX_ATTEMPTS) {
                $this->leaveCodeStep();
                $this->addError('mfaCode', 'Слишком много неверных кодов. Войдите заново.');
                return self::RESULT_RETRY;
            }
            $this->addError('mfaCode', 'Неверный код. Попробуйте ещё раз.');
            return self::RESULT_RETRY;
        }

        if ((int) $user->status !== AdminUser::STATUS_ACTIVE) {
            $this->leaveCodeStep();
            $this->addError('username', 'Ваш аккаунт заблокирован.');
            return self::RESULT_RETRY;
        }

        $remember = $this->rememberMe;
        if ($pendingUser !== null) {
            $state = Yii::$app->session->get(self::MFA_STEP_SESSION_KEY);
            $remember = (bool) ($state['remember'] ?? $remember);
        }
        Yii::$app->cache->delete($this->codeFailuresKey($user));

        return $this->finish($user, $remember);
    }

    private function finish(AdminUser $user, bool $remember): string
    {
        $this->forgetCodeStep();
        $this->resetAttempts();
        $this->_user = $user;

        return Yii::$app->user->login($user, $remember ? 3600 * 24 * 30 * 12 : 0)
            ? self::RESULT_DONE
            : self::RESULT_RETRY;
    }

    private function showCodeStepOf(AdminUser $user): void
    {
        $this->_user = $user;
        $this->username = $user->username;
        $this->authType = (int) $user->auth_type;
        $this->step = self::STEP_CODE;
        $this->passwordConfirmed = true;
    }

    private function isLoginOf(AdminUser $user): bool
    {
        $login = mb_strtolower((string) $this->username);

        return $login === mb_strtolower((string) $user->username) || $login === mb_strtolower((string) $user->email);
    }

    private function leaveCodeStep(): void
    {
        $this->forgetCodeStep();
        $this->passwordConfirmed = false;
        $this->step = self::STEP_LOGIN;
        $this->username = '';
    }

    /**
     * Пользователь, чей пароль уже проверен в этой сессии, или null (нет шага, срок вышел, аккаунт изменился).
     */
    private function codeStepUser(): ?AdminUser
    {
        $state = Yii::$app->session->get(self::MFA_STEP_SESSION_KEY);
        if (!is_array($state) || !isset($state['id'], $state['at'])) {
            return null;
        }
        if (time() - (int) $state['at'] > self::MFA_STEP_TTL) {
            $this->forgetCodeStep();
            return null;
        }

        $user = AdminUser::findOne((int) $state['id']);
        if ($user === null || (int) $user->auth_type !== self::MFA_PASSWORD || (string) $user->mfa_secret === '') {
            $this->forgetCodeStep();
            return null;
        }

        return $user;
    }

    /**
     * @return int число неверных кодов на этом шаге, включая текущий
     */
    private function countCodeStepAttempt(): int
    {
        $state = Yii::$app->session->get(self::MFA_STEP_SESSION_KEY);
        if (!is_array($state)) {
            return self::MAX_ATTEMPTS;
        }
        $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
        Yii::$app->session->set(self::MFA_STEP_SESSION_KEY, $state);

        return $state['attempts'];
    }

    /**
     * Код подходит и ещё не использовался: каждый код принимается один раз.
     */
    private function codeFits(AdminUser $user): bool
    {
        $secret = (string) $user->mfa_secret;
        if ($secret === '') {
            return false;
        }
        $slice = MfaHelper::matchTimeSlice($secret, $this->mfaCode);
        if ($slice === null) {
            return false;
        }

        $key = 'login_code_slice:' . $user->id;
        $last = Yii::$app->cache->get($key);
        if ($last !== false && $slice <= (int) $last) {
            return false;
        }
        Yii::$app->cache->set($key, $slice, 120);

        return true;
    }

    private function normalize(): void
    {
        $this->username = is_string($this->username) ? trim($this->username) : '';
        $this->password = is_string($this->password) ? $this->password : '';
        $this->mfaCode = is_string($this->mfaCode) ? (string) preg_replace('/\s+/', '', $this->mfaCode) : '';
        $this->_user = null;
    }

    // --- Лимит попыток ---

    private function failIfThrottled(string $attribute): bool
    {
        if (!$this->isBlocked()) {
            return false;
        }
        $minutes = max(1, (int) ceil(($this->getLastAttemptTime() + self::BLOCK_DURATION - time()) / 60));
        $this->addError($attribute, "Вы исчерпали лимит попыток. Попробуйте через {$minutes} мин.");

        return true;
    }

    protected function getRateLimitKey()
    {
        return 'login_attempts:' . Yii::$app->request->userIP;
    }

    protected function getAttempts()
    {
        return Yii::$app->cache->get($this->getRateLimitKey()) ?: 0;
    }

    protected function getLastAttemptTime()
    {
        return Yii::$app->cache->get($this->getRateLimitKey() . ':time') ?: 0;
    }

    protected function isBlocked()
    {
        if ($this->getAttempts() < self::MAX_ATTEMPTS) {
            return false;
        }
        if (time() - $this->getLastAttemptTime() < self::BLOCK_DURATION) {
            return true;
        }
        $this->resetAttempts();

        return false;
    }

    protected function registerAttempt()
    {
        $key = $this->getRateLimitKey();
        Yii::$app->cache->set($key, $this->getAttempts() + 1, self::BLOCK_DURATION);
        Yii::$app->cache->set($key . ':time', time(), self::BLOCK_DURATION);
    }

    protected function resetAttempts()
    {
        Yii::$app->cache->delete($this->getRateLimitKey());
        Yii::$app->cache->delete($this->getRateLimitKey() . ':time');
    }

    private function codeFailuresKey(AdminUser $user): string
    {
        return 'login_code_failures:' . $user->id;
    }

    private function codeFailures(AdminUser $user): int
    {
        return (int) Yii::$app->cache->get($this->codeFailuresKey($user));
    }

    private function registerCodeFailure(AdminUser $user): void
    {
        Yii::$app->cache->set($this->codeFailuresKey($user), $this->codeFailures($user) + 1, self::BLOCK_DURATION);
    }
}
