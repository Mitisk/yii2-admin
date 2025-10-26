<?php

namespace Mitisk\Yii2Admin\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%admin_widget_component}}".
 * @property int $id
 * @property int $user_id
 * @property string $component_alias
 * @property int $created_at
 * @property int $updated_at
 */
class AdminWidgetComponent extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%admin_widget_component}}';
    }

    public function rules()
    {
        return [
            [['user_id', 'component_alias'], 'required'],
            [['user_id'], 'integer'],
            [['component_alias'], 'string', 'max' => 128],
        ];
    }
}
