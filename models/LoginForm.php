<?php

namespace Mitisk\Yii2Admin\models;

use Mitisk\Yii2Admin\components\MfaHelper;
use Yii;
use yii\base\Model;

/**
 * LoginForm is the model behind the login form.
 *
 * @property-read AdminUser|null $user
 *
 */
class LoginForm extends Model
{
    /** @var string Логин */
    public $username;
    /** @var string Пароль */
    public $password;
    /** @var int Код двухфакторной аутентификации */
    public $mfaCode;
    /** @var int Тип аутентификации */
    public int $authType = self::PASSWORD;
    /** @var bool Запомнить меня */
    public bool $rememberMe = true;
    /** @var AdminUser|null Экземпляр пользователя */
    private AdminUser|null $_user = null;

    //Только пароль
    const PASSWORD = 0;
    //Пароль и временный код
    const MFA_PASSWORD = 1;
    // Только временный код
    const MFA = 2;



    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['username'], 'required'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
            ['mfaCode', 'validateMfaCode'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if ($this->authType != self::MFA) {
                if (!$user || !$user->validatePassword($this->password)) {
                    $this->addError('password', 'Неверное имя пользователя или пароль.');
                } elseif ($user && $user->status == AdminUser::STATUS_BLOCKED) {
                    $this->addError('username', 'Ваш аккаунт заблокирован.');
                }
            }
        }
    }

    /**
     * Validates the mfaCode.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validateMfaCode($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if (!$user) {
                $this->addError('mfaCode', 'Неверное имя пользователя или пароль.');
            }
            if ($this->authType != self::PASSWORD) {
                if (!$user || !MfaHelper::verifyTotpCode($user->mfa_secret, $this->mfaCode)) {
                    $this->addError('mfaCode', 'Неверный временный код.');
                }
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600*24*30*12 : 0);
        }
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return AdminUser|null
     */
    public function getUser()
    {
        if (!$this->_user) {
            $this->_user = AdminUser::findByUsername($this->username);
        }

        if (!$this->authType && $this->_user) {
            $this->authType = $this->_user?->auth_type;
        }

        return $this->_user;
    }

    /**
     * Получить тип аутентификации
     *
     * @return int
     */
    public function getAuthTypeByUsername()
    {
        if ($this->username) {
            $userType = AdminUser::find()
                ->select('auth_type')
                ->where(['username' => $this->username])
                ->scalar();

            if ($userType) {
                $this->authType = $userType;
                return $userType;
            }
        }
        return self::PASSWORD;
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
}
