<?php

namespace Mitisk\Yii2Admin\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Class AdminWidget
 * @property int $id
 * @property string $alias
 * @property int $user_id
 * @property string $class
 * @property int $ordering
 * @property int $published
 * @property string $created_at
 * @property string $updated_at
 */
class AdminWidget extends ActiveRecord
{
    public static function tableName()
    {
        return 'admin_widget';
    }

    public function rules()
    {
        return [
            [['alias', 'user_id', 'class'], 'required'],
            [['user_id', 'ordering', 'published'], 'integer'],
            [['alias'], 'string', 'max' => 255],
            [['class'], 'string', 'max' => 500],
            [['ordering'], 'default', 'value' => 0],
            [['published'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'alias' => 'Alias',
            'user_id' => 'User ID',
            'class' => 'Widget Class',
            'ordering' => 'Order',
            'published' => 'Published',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->user_id = Yii::$app->user->id;

            // Устанавливаем ordering как максимальный + 1
            if (!$this->ordering) {
                $maxOrdering = static::find()
                    ->where(['user_id' => $this->user_id])
                    ->max('ordering');
                $this->ordering = $maxOrdering ? $maxOrdering + 1 : 1;
            }

            // Генерируем alias из class
            if (!$this->alias) {
                $classParts = explode('\\', $this->class);
                $this->alias = end($classParts);
            }
        }

        return parent::beforeSave($insert);
    }

    public static function getUserWidgets($userId = null, $published = true)
    {
        if ($userId === null) {
            $userId = Yii::$app->user->id;
        }

        $query = static::find()
            ->where(['user_id' => $userId])
            ->orderBy(['ordering' => SORT_ASC]);

        if ($published) {
            $query->andWhere(['published' => 1]);
        }

        return $query->all();
    }
}