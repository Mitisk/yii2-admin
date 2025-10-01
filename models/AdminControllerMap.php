<?php

namespace Mitisk\Yii2Admin\models;

use yii\behaviors\TimestampBehavior;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

final class AdminControllerMap extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public static function tableName() : string
    {
        return '{{%admin_controller_map}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['controller_id', 'class'], 'required'],
            [['controller_id'], 'string', 'max' => 64],
            [['class'], 'string', 'max' => 255],
            [['enabled'], 'boolean'],
            [['controller_id'], 'unique'],
            ['controller_id', fn($attribute) => \Mitisk\Yii2Admin\components\ReservedAlias::validateForControllerMap($this, $attribute)],
            [['config'], 'string'],
            [['config'], 'validateJson'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'enabled' => 'Работает',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
            'config' => 'Конфигурация',
        ];
    }

    public function validateJson(string $attribute) : void
    {
        $val = $this->$attribute;
        if ($val === '' || $val === null) {
            $this->$attribute = null;
            return;
        }
        json_decode((string)$val, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($attribute, 'Некорректный JSON.');
        }
    }

    public function search(array $params) : ActiveDataProvider
    {
        $query = AdminControllerMap::find();

        if ($params && is_array($params)) {
            $search = ArrayHelper::getValue($params, $this->formName() . '.search');

            if ($search) {
                $search = trim($search);
                $query = $query->andWhere(['OR',
                    ['like', 'controller_id', '%' . $search . '%', false],
                    ['like', 'class', '%' . $search . '%', false]
                ]);
            }
        }

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            'pagination' => ['pageSize' => 20],
        ]);
    }
}
