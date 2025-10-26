<?php
namespace Mitisk\Yii2Admin\models;

use yii\db\ActiveRecord;

class AdminNote extends ActiveRecord
{
    public static function tableName()
    {
        return 'admin_note';
    }

    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id'], 'integer'],
            [['text'], 'string'],
        ];
    }
}
