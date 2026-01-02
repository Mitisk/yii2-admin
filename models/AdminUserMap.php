<?php

namespace Mitisk\Yii2Admin\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%admin_user_map}}".
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $form
 * @property string|null $view
 */
class AdminUserMap extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%admin_user_map}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title', 'form', 'view'], 'string', 'max' => 255],
            [['title', 'form', 'view'], 'required'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'form' => 'Form',
            'view' => 'View',
        ];
    }
}
