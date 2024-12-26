<?php
namespace Mitisk\Yii2Admin\fields;

use yii\base\BaseObject;

class FieldsHelper extends BaseObject
{
    /**
     * Возвращает тип поля от его названия для formBuilder
     * @param string $name Название поля
     * @return string
     */
    public static function getFieldsTypeByName($name)
    {
        switch ($name) {
            case 'created_at':
            case 'updated_at':
            case 'deleted_at':
            case 'date':
            case 'time':
            case 'datetime':
                return 'date';
                break;
            case 'text':
            case 'data':
            case 'json':
            case 'textarea':
                return 'textarea';
                break;
            case 'file':
            case 'image':
            case 'files':
            case 'images':
                return 'file';
                break;
            default:
                return 'text';
                break;
        }
    }
}