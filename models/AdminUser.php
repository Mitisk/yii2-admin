<?php

namespace Mitisk\Yii2Admin\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;
use Mitisk\Yii2Admin\models\rbac\AdminUserRbacTrait;
use Mitisk\Yii2Admin\assets\AppAsset;
use Mitisk\Yii2Admin\components\MfaSetup;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property int $online_at
 * @property string $username
 * @property string $name
 * @property string|null $auth_key
 * @property string $password_hash
 * @property string $email
 * @property string $image
 * @property int $status
 * @property int $auth_type Способ входа: LoginForm::PASSWORD | MFA_PASSWORD | MFA
 * @property string|null $mfa_secret Секрет приложения-аутентификатора (Base32)
 */
class AdminUser extends \yii\db\ActiveRecord implements IdentityInterface
{
    use AdminUserRbacTrait;

    const STATUS_BLOCKED = 0;
    const STATUS_ACTIVE = 1;

    public $password;

    public $role;

    public $search;

    public $send_new_password;

    /** @var string|null Код из приложения: подтверждает подключение нового секрета в форме */
    public $mfa_code;

    /** @var mixed «Подключить приложение заново»: выдать новый секрет вместо действующего */
    public $mfa_reset;

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        if ($this->scenario == 'search') {
            return [
                [['username', 'name', 'email', 'status', 'search'], 'safe'],
            ];
        }
        $rules = [
            ['username', 'required'],
            ['username', 'match', 'pattern' => '#^[\w_-]+$#is'],
            ['username', 'unique'],
            [['username'], 'string', 'min' => 2, 'max' => 255],

            ['email', 'required'],
            ['email', 'email'],
            ['email', 'unique'],
            ['email', 'string', 'max' => 255],

            ['status', 'integer'],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],

            // password_hash и image из формы не принимаются: хеш считает saveUser(), путь аватара задают
            // загрузчик и контроллер (по этому пути потом удаляется файл)
            [['password', 'name', 'role', 'search', 'send_new_password'], 'string', 'skipOnEmpty' => true],
        ];

        // Разрешаем auth_type только значения 0, 1 или 2
        $rules[] = ['auth_type', 'in', 'range' => [0, 1, 2]];

        // mfa_secret из формы не принимается: секрет выдаёт сервер (MfaSetup), см. saveUser()
        $rules[] = ['mfa_code', 'string', 'max' => 16];
        $rules[] = ['mfa_reset', 'boolean'];

        return $rules;

    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        $labels = [
            'id' => 'ID',
            'created_at' => 'Создан',
            'updated_at' => 'Обновлён',
            'online_at' => 'Заходил',
            'username' => 'Логин',
            'name' => 'Имя пользователя',
            'email' => 'Email',
            'status' => 'Статус',
            'image' => 'Аватар',

            'password' => 'Пароль',
            'send_new_password' => 'Отправить новый пароль пользователю',
        ];

        $labels['mfa_secret'] = 'Секрет';
        $labels['mfa_code'] = 'Код из приложения';
        $labels['auth_type'] = 'Способ входа';

        return $labels;
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username]);
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return boolean if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->generateAuthKey();
            }
            return true;
        }
        return false;
    }

    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('findIdentityByAccessToken is not implemented.');
    }

    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['search'] = ['id', 'username', 'email', 'status']; // Добавляем сценарий 'search'
        return $scenarios;
    }

    /**
     * Сохранение пользователя в админке: пароль, способ входа и подключение приложения с кодами.
     *
     * @return bool
     * @throws \yii\db\Exception
     */
    public function saveUser() : bool
    {
        $valid = $this->validate();
        // Обе проверки выполняются всегда: человек видит все замечания сразу
        $valid = $this->applyPassword() && $valid;
        $valid = $this->applyMfa() && $valid;
        if (!$valid) {
            return false;
        }

        $setupId = $this->getMfaSetupId();
        if ($this->save(false)) {
            MfaSetup::forget($setupId);
            MfaSetup::forget((int) $this->id);

            if ($this->send_new_password && $this->password && $this->email && $this->usesPassword()) {
                $tplId = Yii::$app->settings->get(self::class, 'mail_template_new_password');

                if ($tplId) {
                    // Через контейнер: настройка MailService проекта (например, mailer вне прода) действует и здесь
                    $mailService = Yii::createObject(\Mitisk\Yii2Admin\components\MailService::class);
                    $mailService->send($tplId, $this->email, [
                        'PASSWORD' => $this->password
                    ]);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Вход с паролем (типы «только пароль» и «пароль и код»).
     */
    public function usesPassword(): bool
    {
        return (int) $this->auth_type !== LoginForm::MFA;
    }

    /**
     * Вход с кодом из приложения (типы «пароль и код» и «только код»).
     */
    public function usesCode(): bool
    {
        return (int) $this->auth_type !== LoginForm::PASSWORD;
    }

    /**
     * Приложение-аутентификатор подключено: секрет сохранён в базе.
     */
    public function hasMfaApp(): bool
    {
        return !$this->isNewRecord && (string) $this->getOldAttribute('mfa_secret') !== '';
    }

    /**
     * Пароля у пользователя нет: он новый или входил только по коду.
     */
    public function hasNoPassword(): bool
    {
        return $this->isNewRecord
            || (string) $this->getOldAttribute('password_hash') === ''
            || (int) $this->getOldAttribute('auth_type') === LoginForm::MFA;
    }

    /**
     * Номер подключения в MfaSetup: id пользователя, для нового — 0.
     */
    public function getMfaSetupId(): int
    {
        return $this->isNewRecord ? 0 : (int) $this->getOldPrimaryKey();
    }

    /**
     * Пароль: обязателен там, где без него человек не войдёт; при входе «только код» старый пароль гасится.
     */
    private function applyPassword(): bool
    {
        $password = is_string($this->password) ? $this->password : '';

        if ($this->usesPassword()) {
            if ($password !== '') {
                $this->password_hash = Yii::$app->security->generatePasswordHash($password);
            } elseif ($this->hasNoPassword()) {
                $this->addError('password', $this->isNewRecord
                    ? 'Задайте пароль: без него пользователь не сможет войти.'
                    : 'Задайте пароль: раньше вход был только по коду, пароля у пользователя нет.');
                return false;
            }
            return true;
        }

        // «Только код»: пароль во входе не участвует. Прежний перестаёт действовать —
        // при возврате к паролю его придётся задать заново.
        if (!$this->hasNoPassword() || (string) $this->password_hash === '') {
            $this->password_hash = Yii::$app->security->generatePasswordHash(
                Yii::$app->security->generateRandomString(40)
            );
        }
        $this->password = null;

        return true;
    }

    /**
     * Секрет приложения: без кода из приложения новый секрет не сохраняется, действующий — не меняется.
     */
    private function applyMfa(): bool
    {
        $stored = $this->isNewRecord ? '' : (string) $this->getOldAttribute('mfa_secret');

        if (!$this->usesCode()) {
            // Вход по коду выключен: секрет удаляем, при следующем включении будет новый
            $this->mfa_secret = null;
            return true;
        }
        if ($stored !== '' && !$this->mfa_reset) {
            $this->mfa_secret = $stored;
            return true;
        }

        $this->mfa_secret = $stored !== '' ? $stored : null;
        $code = is_string($this->mfa_code) ? trim($this->mfa_code) : '';
        if ($code === '') {
            $this->addError('mfa_code', 'Введите код из приложения: без него вход по коду не включится.');
            return false;
        }
        $secret = MfaSetup::confirm($this->getMfaSetupId(), $code);
        if ($secret === null) {
            $this->addError('mfa_code', 'Код не подошёл. Отсканируйте QR-код ниже и введите свежий код из приложения.');
            return false;
        }
        $this->mfa_secret = $secret;

        return true;
    }

    /**
     * Метод поиска.
     *
     * @param array $params Параметры запроса (например, $_GET).
     * @return ActiveDataProvider
     */
    public function search($params, $ids = [])
    {
        $this->scenario = 'search';

        // Создаем запрос к базе данных
        $query = AdminUser::find();
        if ($ids) {
            $query->andWhere(['in', 'id', $ids]);
        }

        if ($params && is_array($params)) {
            $search = ArrayHelper::getValue($params, $this->formName() . '.search');

            if ($search) {
                $search = trim($search);
                $query = $query->andWhere(['OR',
                    ['like', 'username', '%' . $search . '%', false],
                    ['like', 'name', '%' . $search . '%', false],
                    ['like', 'email', '%' . $search . '%', false]
                ]);
            }
        }

        // Настройка провайдера данных
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20, // Количество элементов на странице
            ],
            'sort' => [
                'defaultOrder' => [
                    'id' => SORT_DESC, // Сортировка по умолчанию
                ],
            ],
        ]);

        return $dataProvider;
    }

    /**
     * Return has settings
     * @return bool
     */
    public function hasSettings() : bool
    {
        if (Yii::$app->user->can('admin')) {
            return Settings::find()->where(['model_name' => self::class])->exists();
        }
        return false;
    }

    /**
     * Возвращает ссылку на аватар или заглушку.
     * @return string
     */
    public function getAvatar()
    {
        $image = $this->getAttribute('image');
        if ($image) {
            return $image;
        }

        return Yii::$app->assetManager->getBundle(AppAsset::class)->baseUrl . '/img/no_avatar.png';
    }

    /**
     * Метод для удаления аватарки пользователя.
     * @return void
     */
    public function deleteImage()
    {
        if ($this->image && !str_contains( $this->image, 'noPhoto')) {

            $path = Yii::getAlias('@webroot') . str_replace('/web', '', $this->image);
            if (file_exists($path)) {
                unlink($path);
            }

            $this->updateAttributes(['image' => null]);
        }
    }
}
