<?php

namespace Mitisk\Yii2Admin\models;

class MailTemplate extends \yii\db\ActiveRecord
{
    /**
     * @return string name of db table
     */
    public static function tableName()
    {
        return '{{%mail_templates}}';
    }

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['id', 'name', 'alias', 'from', 'to', 'subject', 'content', 'published'], 'safe'],
            [['id', 'published'], 'number', 'integerOnly' => true],
            [['name'], 'required', 'on'=>['insert', 'update']],
            [['alias'], 'Mitisk\Yii2Admin\components\AliasValidator']
        ];
    }

    /**
     * @return array labels of attributes
     */
    public function attributeLabels()
    {
        return [
            'name' => 'Название',
            'alias' => 'Алиас',
            'from' => 'От кого',
            'to' => 'Кому',
            'content' => 'Контент',
            'subject' => 'Тема',
            'published' => 'Опубликовано',
        ];
    }
}
