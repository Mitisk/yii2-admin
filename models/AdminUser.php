<?php

namespace Mitisk\Yii2Admin\models;

use Yii;
use yii\base\Model;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
use yii\rbac\Item;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property int $last_login_at
 * @property string $username
 * @property string $name
 * @property string|null $auth_key
 * @property string $password_hash
 * @property string $email
 * @property string $image
 * @property int $status
 */
class AdminUser extends \yii\db\ActiveRecord implements IdentityInterface
{
    const STATUS_BLOCKED = 0;
    const STATUS_ACTIVE = 1;

    public $password;

    public $role;

    public $search;

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
        return [
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

            [['password', 'name', 'password_hash', 'role', 'image', 'search'], 'string', 'skipOnEmpty' => true],
        ];
    }

    /*public function rules()
    {
        return [
            [['created_at', 'updated_at', 'username', 'password_hash', 'email'], 'required'],
            [['created_at', 'updated_at', 'status'], 'integer'],
            [['username', 'password_hash', 'email'], 'string', 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],
        ];
    }*/

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Создан',
            'updated_at' => 'Обновлён',
            'last_login_at' => 'Заходил',
            'username' => 'Логин',
            'name' => 'Имя пользователя',
            'email' => 'Email',
            'status' => 'Статус',
            'image' => 'Аватар',
            'password' => 'Пароль',
        ];
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
     * Сохранение пользователя в админке
     * @return bool
     * @throws \yii\db\Exception
     */
    public function saveUser() : bool
    {
        if ($this->isNewRecord && !$this->password) {
            $this->addError('password', 'Пароль обязателен для заполнения');
            return false;
        }
        if ($this->validate()) {

            if ($this->password) {
                $this->password_hash = Yii::$app->security->generatePasswordHash($this->password);
            }

            return $this->save();
        }
        return false;
    }

    /**
     * Метод поиска.
     *
     * @param array $params Параметры запроса (например, $_GET).
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $this->scenario = 'search';

        // Создаем запрос к базе данных
        $query = AdminUser::find();

        $search = trim(ArrayHelper::getValue($params, $this->formName() . '.search'));

        if ($search) {
            $query = $query->andWhere(['OR',
                ['like', 'username', '%' . $search . '%', false],
                ['like', 'name', '%' . $search . '%', false],
                ['like', 'email', '%' . $search . '%', false]
            ]);
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
